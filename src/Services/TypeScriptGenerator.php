<?php

namespace SynergiTech\TypeScriptGenerator\Services;

use SynergiTech\TypeScriptGenerator\Mappers\TypeMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

class TypeScriptGenerator
{
    protected array $config;
    protected Filesystem $files;

    /** @var array<string, string> Model FQCN → TypeScript interface name */
    protected array $modelMap = [];

    /** @var array<string, string> Enum FQCN → TypeScript enum name */
    protected array $enumMap = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->files = new Filesystem();
    }

    // -----------------------------------------------------------------------
    //  Public API
    // -----------------------------------------------------------------------

    /**
     * Run the full generation pipeline: enums first, then models.
     *
     * @return array{
     *   generated: string[],
     *   skipped: string[],
     *   errors: array<string, string>,
     *   enums_generated: string[],
     *   enum_errors: array<string, string>
     * }
     */
    public function generate(bool $withRelationships = false): array
    {
        // Generate enums first so the enum map is available during model generation
        $enumResults = $this->generateEnums();

        $models = $this->discoverModels();
        $results = [
            'generated' => [],
            'skipped' => [],
            'errors' => [],
            'enums_generated' => $enumResults['generated'],
            'enum_errors' => $enumResults['errors'],
        ];

        foreach ($models as $modelClass) {
            $this->modelMap[$modelClass] = class_basename($modelClass);
        }

        $outputDir = $this->resolvePath($this->config['output_directory']);
        $this->ensureDirectory($outputDir);

        foreach ($models as $modelClass) {
            if (in_array($modelClass, $this->config['excluded_models'] ?? [], true)) {
                $results['skipped'][] = $modelClass;
                continue;
            }

            try {
                $typeDefinition = $this->generateForModel($modelClass, $withRelationships);
                $filename = class_basename($modelClass) . '.d.ts';
                $this->files->put($outputDir . '/' . $filename, $typeDefinition);
                $results['generated'][] = $modelClass;
            } catch (Throwable $e) {
                $results['errors'][$modelClass] = $e->getMessage();
            }
        }

        $this->generateModelIndex($outputDir, $results['generated']);

        return $results;
    }

    // -----------------------------------------------------------------------
    //  Model Discovery
    // -----------------------------------------------------------------------

    /**
     * Scan the configured model directory and return all Eloquent model FQCNs.
     *
     * @return string[]
     */
    public function discoverModels(): array
    {
        $directory = $this->resolvePath($this->config['model_directory']);

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $namespace = $this->config['model_namespace'];
        $models = [];

        foreach ($this->files->allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = $file->getRelativePathname();
            $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if (
                $reflection->isAbstract() ||
                $reflection->isInterface() ||
                $reflection->isTrait() ||
                ! $reflection->isSubclassOf(Model::class)
            ) {
                continue;
            }

            $models[] = $className;
        }

        sort($models);

        return $models;
    }

    // -----------------------------------------------------------------------
    //  Enum Discovery & Generation
    // -----------------------------------------------------------------------

    /**
     * Scan the configured enum directory and return all backed enum FQCNs.
     *
     * @return string[]
     */
    public function discoverEnums(): array
    {
        $directory = $this->resolvePath($this->config['enum_directory'] ?? 'app/Enum');

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $namespace = $this->config['enum_namespace'] ?? 'App\\Enum';
        $enums = [];

        foreach ($this->files->allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = $file->getRelativePathname();
            $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if (! enum_exists($className)) {
                continue;
            }

            $reflection = new ReflectionEnum($className);

            if (! $reflection->isBacked()) {
                continue;
            }

            $enums[] = $className;
        }

        sort($enums);

        return $enums;
    }

    /**
     * Generate TypeScript enum files and populate $this->enumMap.
     *
     * @return array{generated: string[], errors: array<string, string>}
     */
    public function generateEnums(): array
    {
        $enums = $this->discoverEnums();
        $results = ['generated' => [], 'errors' => []];

        if (empty($enums)) {
            return $results;
        }

        $outputDir = $this->resolvePath($this->config['enum_output_directory'] ?? 'resources/js/types/enums');
        $this->ensureDirectory($outputDir);

        foreach ($enums as $enumClass) {
            try {
                $definition = $this->generateForEnum($enumClass);
                $filename = class_basename($enumClass) . '.ts';
                $this->files->put($outputDir . '/' . $filename, $definition);
                $this->enumMap[$enumClass] = class_basename($enumClass);
                $results['generated'][] = $enumClass;
            } catch (Throwable $e) {
                $results['errors'][$enumClass] = $e->getMessage();
            }
        }

        if (! empty($results['generated'])) {
            $this->generateEnumIndex($outputDir, $results['generated']);
        }

        return $results;
    }

    /**
     * Generate the TypeScript enum content for a single backed enum.
     */
    public function generateForEnum(string $enumClass): string
    {
        $reflection = new ReflectionEnum($enumClass);
        $enumName = class_basename($enumClass);
        $backingType = $reflection->getBackingType()?->getName() ?? 'string';

        $lines = [];
        $lines[] = '// Auto-generated by laravel-typescript-generator';
        $lines[] = '// Enum: ' . $enumClass;
        $lines[] = '// Generated at: ' . now()->toIso8601String();
        $lines[] = '';
        $lines[] = "export enum {$enumName} {";

        foreach ($reflection->getCases() as $case) {
            $value = $case->getBackingValue();
            $valueStr = $backingType === 'string' ? "'{$value}'" : (string) $value;
            $lines[] = "  {$case->getName()} = {$valueStr},";
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    // -----------------------------------------------------------------------
    //  Per-Model Generation
    // -----------------------------------------------------------------------

    /**
     * Generate the full .d.ts content for a single model.
     */
    public function generateForModel(string $modelClass, bool $withRelationships = false): string
    {
        /** @var Model $instance */
        $instance = new $modelClass();

        $interfaceName = class_basename($modelClass);
        $properties = $this->resolveProperties($instance, $modelClass);

        $lines = [];
        $lines[] = '// Auto-generated by laravel-typescript-generator';
        $lines[] = '// Model: ' . $modelClass;
        $lines[] = '// Generated at: ' . now()->toIso8601String();
        $lines[] = '';

        // Collect enum imports from casted properties
        $enumImports = [];
        foreach ($properties as $prop) {
            if (isset($prop['enum_class']) && $prop['enum_class'] !== null) {
                $enumName = $this->enumMap[$prop['enum_class']] ?? null;
                if ($enumName !== null && ! in_array($enumName, $enumImports, true)) {
                    $enumImports[] = $enumName;
                }
            }
        }

        // Collect model imports for relationship types
        $modelImports = [];
        if ($withRelationships) {
            $relationships = $this->resolveRelationships($instance);
            foreach ($relationships as $rel) {
                $relatedType = $rel['typescript_type'];
                preg_match('/^([A-Za-z_][A-Za-z0-9_]*)/', $relatedType, $matches);
                $baseType = $matches[1] ?? '';
                if ($baseType !== '' && $baseType !== 'unknown' && $baseType !== $interfaceName && ! in_array($baseType, $modelImports, true)) {
                    $modelImports[] = $baseType;
                }
            }
        }

        // Write enum imports (from ../enums/)
        foreach ($enumImports as $enumName) {
            $lines[] = "import type { {$enumName} } from '../enums/{$enumName}';";
        }

        // Write model imports (from sibling files)
        foreach ($modelImports as $import) {
            $lines[] = "import type { {$import} } from './{$import}';";
        }

        if (count($enumImports) > 0 || count($modelImports) > 0) {
            $lines[] = '';
        }

        $lines[] = "export interface {$interfaceName} {";

        foreach ($properties as $prop) {
            $nullable = $prop['nullable'];
            $tsType = $prop['typescript_type'];
            $comment = $prop['comment'] ?? '';

            if ($comment) {
                $lines[] = "  /** {$comment} */";
            }

            if ($nullable && ($this->config['nullable_style'] ?? 'union') === 'optional') {
                $lines[] = "  {$prop['name']}?: {$tsType};";
            } elseif ($nullable) {
                $lines[] = "  {$prop['name']}: {$tsType} | null;";
            } else {
                $lines[] = "  {$prop['name']}: {$tsType};";
            }
        }

        if ($withRelationships) {
            $relationships = $this->resolveRelationships($instance);
            if (count($relationships) > 0) {
                $lines[] = '';
                $lines[] = '  // Relationships';
                foreach ($relationships as $rel) {
                    $lines[] = "  {$rel['name']}?: {$rel['typescript_type']};";
                }
            }
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    // -----------------------------------------------------------------------
    //  Property Resolution (Schema + Casts + Overrides)
    // -----------------------------------------------------------------------

    /**
     * Merge database schema columns with model casts to produce the final
     * list of typed properties.
     *
     * @return array<int, array{name: string, typescript_type: string, nullable: bool, comment: string, enum_class: string|null}>
     */
    protected function resolveProperties(Model $instance, string $modelClass): array
    {
        $table = $instance->getTable();
        $connection = $instance->getConnectionName();

        $columns = $this->getTableColumns($table, $connection);
        $casts = $instance->getCasts();
        $overrides = $this->config['type_overrides'][$modelClass] ?? [];

        $properties = [];

        foreach ($columns as $column) {
            $name = $column['name'];

            if (
                ! ($this->config['include_timestamps'] ?? false) &&
                ! $instance->usesTimestamps() &&
                in_array($name, [$instance->getCreatedAtColumn(), $instance->getUpdatedAtColumn()], true)
            ) {
                continue;
            }

            $enumClass = null;

            if (isset($overrides[$name])) {
                $tsType = $overrides[$name];
            } elseif (isset($casts[$name])) {
                $castValue = $casts[$name];
                if (isset($this->enumMap[$castValue])) {
                    $tsType = $this->enumMap[$castValue];
                    $enumClass = $castValue;
                } else {
                    $tsType = TypeMapper::fromLaravelCast($castValue);
                }
            } else {
                $tsType = TypeMapper::fromDatabaseType($column['type']);
            }

            $properties[] = [
                'name' => $name,
                'typescript_type' => $tsType,
                'nullable' => $column['nullable'],
                'comment' => $this->buildComment($column, $casts[$name] ?? null),
                'enum_class' => $enumClass,
            ];
        }

        return $properties;
    }

    /**
     * Retrieve column metadata from the database schema.
     *
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    protected function getTableColumns(string $table, ?string $connection): array
    {
        $schemaBuilder = Schema::connection($connection);

        if (method_exists($schemaBuilder, 'getColumns')) {
            return collect($schemaBuilder->getColumns($table))
                ->map(fn (array $col) => [
                    'name' => $col['name'],
                    'type' => $col['type_name'] ?? $col['type'] ?? 'string',
                    'nullable' => $col['nullable'] ?? true,
                ])
                ->all();
        }

        $columnNames = $schemaBuilder->getColumnListing($table);

        return collect($columnNames)
            ->map(fn (string $name) => [
                'name' => $name,
                'type' => $schemaBuilder->getColumnType($table, $name),
                'nullable' => true,
            ])
            ->all();
    }

    /**
     * Build a short JSDoc comment describing the source of the type.
     */
    protected function buildComment(array $column, ?string $cast): string
    {
        $parts = [];
        $parts[] = 'DB: ' . $column['type'];

        if ($cast) {
            $parts[] = 'Cast: ' . $cast;
        }

        if ($column['nullable']) {
            $parts[] = 'nullable';
        }

        return implode(' | ', $parts);
    }

    // -----------------------------------------------------------------------
    //  Relationship Resolution
    // -----------------------------------------------------------------------

    /**
     * Detect relationship methods on the model via reflection.
     *
     * @return array<int, array{name: string, type: string, related_model: string, typescript_type: string}>
     */
    protected function resolveRelationships(Model $instance): array
    {
        $reflection = new ReflectionClass($instance);
        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== get_class($instance)) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $returnTypeName = $returnType->getName();

            if (! is_subclass_of($returnTypeName, Relation::class) && $returnTypeName !== Relation::class) {
                continue;
            }

            try {
                /** @var Relation $relation */
                $relation = $instance->{$method->getName()}();
                $relatedClass = get_class($relation->getRelated());
                $relationType = class_basename($relation);
                $relatedTsType = $this->modelMap[$relatedClass] ?? class_basename($relatedClass);

                $relationships[] = [
                    'name' => $method->getName(),
                    'type' => $relationType,
                    'related_model' => $relatedClass,
                    'typescript_type' => TypeMapper::fromRelationship($relationType, $relatedTsType),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $relationships;
    }

    // -----------------------------------------------------------------------
    //  Index / Barrel Files
    // -----------------------------------------------------------------------

    /**
     * Generate an index.d.ts that re-exports all generated model interfaces.
     *
     * @param  string[]  $generatedModels  FQCNs of successfully generated models
     */
    protected function generateModelIndex(string $outputDir, array $generatedModels): void
    {
        $lines = ['// Auto-generated barrel file — do not edit manually', ''];

        foreach ($generatedModels as $modelClass) {
            $name = class_basename($modelClass);
            $lines[] = "export type { {$name} } from './{$name}';";
        }

        $lines[] = '';

        $this->files->put($outputDir . '/index.d.ts', implode("\n", $lines));
    }

    /**
     * Generate an index.ts that re-exports all generated enums.
     *
     * @param  string[]  $generatedEnums  FQCNs of successfully generated enums
     */
    protected function generateEnumIndex(string $outputDir, array $generatedEnums): void
    {
        $lines = ['// Auto-generated barrel file — do not edit manually', ''];

        foreach ($generatedEnums as $enumClass) {
            $name = class_basename($enumClass);
            $lines[] = "export { {$name} } from './{$name}';";
        }

        $lines[] = '';

        $this->files->put($outputDir . '/index.ts', implode("\n", $lines));
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    protected function ensureDirectory(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Resolve a config path to an absolute filesystem path.
     * Absolute paths are returned as-is; relative paths are resolved via base_path().
     */
    protected function resolvePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}