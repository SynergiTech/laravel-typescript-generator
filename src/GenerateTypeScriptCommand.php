<?php

namespace SynergiTech\TypeScriptGenerator\Commands;

use SynergiTech\TypeScriptGenerator\Services\TypeScriptGenerator;
use Illuminate\Console\Command;

class GenerateTypeScriptCommand extends Command
{
    protected $signature = 'types:generate
                            {--with-relationships : Include relationship properties in the generated types}
                            {--without-relationships : Exclude relationship properties (overrides config)}
                            {--model=* : Generate types only for specific models (short class name, e.g. User)}
                            {--dry-run : Preview what would be generated without writing files}';

    protected $description = 'Generate TypeScript type definitions from Eloquent models';

    public function handle(TypeScriptGenerator $generator): int
    {
        $this->components->info('Scanning for Eloquent models…');

        $withRelationships = $this->resolveRelationshipsFlag();
        $filterModels = $this->option('model');
        $dryRun = (bool) $this->option('dry-run');

        // Discover all models first (so the user can see what was found)
        $discovered = $generator->discoverModels();

        if (empty($discovered)) {
            $this->components->warn('No models found in the configured namespace.');

            return self::FAILURE;
        }

        $this->components->bulletList(
            array_map(fn (string $m) => class_basename($m) . " <fg=gray>({$m})</>", $discovered)
        );

        $this->newLine();

        if ($dryRun) {
            $this->components->info('[dry-run] Would generate ' . count($discovered) . ' type definition(s). No files written.');

            return self::SUCCESS;
        }

        $results = $generator->generate($withRelationships);

        // Report
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

        $outputDir = config('typescript-generator.output_directory', 'resources/js/types');
        $count = count($results['generated']);

        $this->components->info(
            "Generated {$count} type definition(s) → {$outputDir}/"
        );

        if ($withRelationships) {
            $this->components->info('Relationships: included');
        }

        return count($results['errors']) > 0 ? self::FAILURE : self::SUCCESS;
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
}
