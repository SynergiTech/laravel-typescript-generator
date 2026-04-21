<?php

namespace SynergiTech\TypeScriptGenerator;

use Illuminate\Support\ServiceProvider;
use SynergiTech\TypeScriptGenerator\Commands\GenerateTypeScriptCommand;
use SynergiTech\TypeScriptGenerator\Services\ResourceGenerator;
use SynergiTech\TypeScriptGenerator\Services\TypeScriptGenerator;

class TypeScriptGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/typescript-generator.php',
            'typescript-generator'
        );

        $this->app->singleton(TypeScriptGenerator::class, function ($app) {
            return new TypeScriptGenerator($app['config']['typescript-generator']);
        });

        $this->app->singleton(ResourceGenerator::class, function ($app) {
            return new ResourceGenerator($app['config']['typescript-generator']);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypeScriptCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/typescript-generator.php' => config_path('typescript-generator.php'),
            ], 'typescript-generator-config');
        }
    }
}
