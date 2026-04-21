<?php

namespace SynergiTech\TypeScriptGenerator\Commands;

use SynergiTech\TypeScriptGenerator\Services\ResourceGenerator;
use SynergiTech\TypeScriptGenerator\Services\TypeScriptGenerator;
use Illuminate\Console\Command;

class GenerateTypeScriptCommand extends Command
{
    protected $signature = 'types:generate
                            {--with-relationships : Include relationship properties in the generated types}
                            {--without-relationships : Exclude relationship properties (overrides config)}
                            {--with-resources : Generate types for API resources}
                            {--without-resources : Skip resource type generation (overrides config)}
                            {--model=* : Generate types only for specific models (short class name, e.g. User)}
                            {--dry-run : Preview what would be generated without writing files}';

    protected $description = 'Generate TypeScript type definitions from Eloquent models, enums, and API resources';

    public function handle(TypeScriptGenerator $generator, ResourceGenerator $resourceGenerator): int
    {
        $withRelationships = $this->resolveRelationshipsFlag();
        $withResources = $this->resolveResourcesFlag();
        $dryRun = (bool) $this->option('dry-run');

        // --- Enums ---
        $this->components->info('Scanning for backed enums…');

        $discoveredEnums = $generator->discoverEnums();

        if (! empty($discoveredEnums)) {
            $this->components->bulletList(
                array_map(fn (string $e) => class_basename($e) . " <fg=gray>({$e})</>", $discoveredEnums)
            );
            $this->newLine();
        } else {
            $this->components->warn('No backed enums found in the configured namespace.');
            $this->newLine();
        }

        // --- Models ---
        $this->components->info('Scanning for Eloquent models…');

        $discovered = $generator->discoverModels();

        if (empty($discovered)) {
            $this->components->warn('No models found in the configured namespace.');

            return self::FAILURE;
        }

        $this->components->bulletList(
            array_map(fn (string $m) => class_basename($m) . " <fg=gray>({$m})</>", $discovered)
        );
        $this->newLine();

        // --- Resources ---
        $discoveredResources = [];
        if ($withResources) {
            $this->components->info('Scanning for API resources…');

            $discoveredResources = $resourceGenerator->discoverResources();

            if (! empty($discoveredResources)) {
                $this->components->bulletList(
                    array_map(fn (string $r) => class_basename($r) . " <fg=gray>({$r})</>", $discoveredResources)
                );
            } else {
                $this->components->warn('No API resources found in the configured namespace.');
            }

            $this->newLine();
        }

        if ($dryRun) {
            $summary = count($discoveredEnums) . ' enum(s), ' . count($discovered) . ' model type(s)';
            if ($withResources) {
                $summary .= ', ' . count($discoveredResources) . ' resource(s)';
            }
            $this->components->info("[dry-run] Would generate {$summary}. No files written.");

            return self::SUCCESS;
        }

        $results = $generator->generate($withRelationships);

        // Report enums
        foreach ($results['enums_generated'] as $enumClass) {
            $this->components->twoColumnDetail(
                '<fg=green>✓</> ' . class_basename($enumClass),
                '<fg=gray>' . class_basename($enumClass) . '.ts</>'
            );
        }

        foreach ($results['enum_errors'] as $enumClass => $error) {
            $this->components->twoColumnDetail(
                '<fg=red>✗</> ' . class_basename($enumClass),
                "<fg=red>{$error}</>"
            );
        }

        if (! empty($results['enums_generated']) || ! empty($results['enum_errors'])) {
            $this->newLine();
        }

        // Report models
        foreach ($results['generated'] as $model) {
            $this->components->twoColumnDetail(
                '<fg=green>✓</> ' . class_basename($model),
                '<fg=gray>' . class_basename($model) . '.d.ts</>'
            );
        }

        foreach ($results['skipped'] as $model) {
            $this->components->twoColumnDetail(
                '<fg=yellow>⊘</> ' . class_basename($model),
                '<fg=gray>skipped (excluded)</>'
            );
        }

        foreach ($results['errors'] as $model => $error) {
            $this->components->twoColumnDetail(
                '<fg=red>✗</> ' . class_basename($model),
                "<fg=red>{$error}</>"
            );
        }

        $this->newLine();

        // Report resources
        $resourceResults = ['generated' => [], 'skipped' => [], 'errors' => []];
        if ($withResources) {
            $resourceResults = $resourceGenerator->generate();

            foreach ($resourceResults['generated'] as $resourceClass) {
                $this->components->twoColumnDetail(
                    '<fg=green>✓</> ' . class_basename($resourceClass),
                    '<fg=gray>' . class_basename($resourceClass) . '.d.ts</>'
                );
            }

            foreach ($resourceResults['skipped'] as $resourceClass) {
                $this->components->twoColumnDetail(
                    '<fg=yellow>⊘</> ' . class_basename($resourceClass),
                    '<fg=gray>skipped (excluded)</>'
                );
            }

            foreach ($resourceResults['errors'] as $resourceClass => $error) {
                $this->components->twoColumnDetail(
                    '<fg=red>✗</> ' . class_basename($resourceClass),
                    "<fg=red>{$error}</>"
                );
            }

            if (! empty($resourceResults['generated']) || ! empty($resourceResults['errors'])) {
                $this->newLine();
            }
        }

        $enumOutputDir = config('typescript-generator.enum_output_directory', 'resources/js/types/enums');
        $modelOutputDir = config('typescript-generator.output_directory', 'resources/js/types/models');
        $enumCount = count($results['enums_generated']);
        $modelCount = count($results['generated']);

        $this->components->info("Generated {$enumCount} enum(s) → {$enumOutputDir}/");
        $this->components->info("Generated {$modelCount} model type(s) → {$modelOutputDir}/");

        if ($withResources) {
            $resourceOutputDir = config('typescript-generator.resource_output_directory', 'resources/js/types/resources');
            $resourceCount = count($resourceResults['generated']);
            $this->components->info("Generated {$resourceCount} resource type(s) → {$resourceOutputDir}/");
        }

        if ($withRelationships) {
            $this->components->info('Relationships: included');
        }

        $hasErrors = count($results['errors']) > 0
            || count($results['enum_errors']) > 0
            || ($withResources && count($resourceResults['errors']) > 0);

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Determine whether relationships should be included.
     * CLI flags override the config value.
     */
    protected function resolveRelationshipsFlag(): bool
    {
        if ($this->option('with-relationships')) {
            return true;
        }

        if ($this->option('without-relationships')) {
            return false;
        }

        return (bool) config('typescript-generator.include_relationships', false);
    }

    /**
     * Determine whether resource types should be generated.
     * CLI flags override the config value.
     */
    protected function resolveResourcesFlag(): bool
    {
        if ($this->option('with-resources')) {
            return true;
        }

        if ($this->option('without-resources')) {
            return false;
        }

        return (bool) config('typescript-generator.include_resources', false);
    }
}
