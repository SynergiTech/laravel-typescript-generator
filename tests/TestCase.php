<?php

namespace SynergiTech\TypeScriptGenerator\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use SynergiTech\TypeScriptGenerator\TypeScriptGeneratorServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $outputBase;

    protected function getApplicationBasePath(): string
    {
        return dirname(__DIR__);
    }

    protected function getPackageProviders($app): array
    {
        return [TypeScriptGeneratorServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $this->outputBase = sys_get_temp_dir() . '/ts-gen-tests-' . uniqid();

        $app['config']->set('typescript-generator', [
            'model_namespace'          => 'SynergiTech\\TypeScriptGenerator\\Tests\\Fixtures\\Models',
            'model_directory'          => 'tests/Fixtures/Models',
            'output_directory'         => $this->outputBase . '/models',
            'enum_namespace'           => 'SynergiTech\\TypeScriptGenerator\\Tests\\Fixtures\\Enums',
            'enum_directory'           => 'tests/Fixtures/Enums',
            'enum_output_directory'    => $this->outputBase . '/enums',
            'resource_namespace'       => 'SynergiTech\\TypeScriptGenerator\\Tests\\Fixtures\\Resources',
            'resource_directory'       => 'tests/Fixtures/Resources',
            'resource_output_directory'=> $this->outputBase . '/resources',
            'include_relationships'    => false,
            'include_timestamps'       => true,
            'include_resources'        => false,
            'nullable_style'           => 'union',
            'excluded_models'          => [],
            'excluded_resources'       => [],
            'type_overrides'           => [],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('is_admin')->default(false);
            $table->json('metadata')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');

        parent::tearDown();
    }
}
