<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SynergiTech\TypeScriptGenerator\Services\TypeScriptGenerator;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Models\Post;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Models\User;
use SynergiTech\TypeScriptGenerator\Tests\TestCase;

class ModelGenerationTest extends TestCase
{
    private TypeScriptGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(TypeScriptGenerator::class);
    }

    // -----------------------------------------------------------------------
    //  Discovery
    // -----------------------------------------------------------------------

    #[Test]
    public function it_discovers_eloquent_models(): void
    {
        $models = $this->generator->discoverModels();

        $this->assertContains(Post::class, $models);
        $this->assertContains(User::class, $models);
    }

    #[Test]
    public function it_returns_models_sorted_alphabetically(): void
    {
        $models = $this->generator->discoverModels();

        $sorted = $models;
        sort($sorted);

        $this->assertSame($sorted, $models);
    }

    #[Test]
    public function it_skips_excluded_models(): void
    {
        config(['typescript-generator.excluded_models' => [User::class]]);
        $generator = new TypeScriptGenerator(config('typescript-generator'));

        $results = $generator->generate();

        $this->assertNotContains(User::class, $results['generated']);
        $this->assertContains(User::class, $results['skipped']);
    }

    // -----------------------------------------------------------------------
    //  Type generation — basic DB columns
    // -----------------------------------------------------------------------

    #[Test]
    public function it_generates_an_interface_for_a_model(): void
    {
        $output = $this->generator->generateForModel(Post::class);

        $this->assertStringContainsString('export interface Post {', $output);
    }

    #[Test]
    public function it_maps_bigint_id_to_number(): void
    {
        $output = $this->generator->generateForModel(Post::class);

        $this->assertStringContainsString('id: number;', $output);
    }

    #[Test]
    public function it_maps_varchar_to_string(): void
    {
        $output = $this->generator->generateForModel(Post::class);

        $this->assertStringContainsString('title: string;', $output);
    }

    #[Test]
    public function it_marks_nullable_columns_with_null_union(): void
    {
        $output = $this->generator->generateForModel(Post::class);

        $this->assertStringContainsString('body: string | null;', $output);
    }

    // -----------------------------------------------------------------------
    //  Type generation — casts
    // -----------------------------------------------------------------------

    #[Test]
    public function it_uses_cast_type_over_database_type(): void
    {
        $output = $this->generator->generateForModel(User::class);

        $this->assertStringContainsString('is_admin: boolean;', $output);
    }

    #[Test]
    public function it_maps_array_cast_to_unknown_array(): void
    {
        $output = $this->generator->generateForModel(User::class);

        $this->assertStringContainsString('metadata: unknown[] | null;', $output);
    }

    // -----------------------------------------------------------------------
    //  Type generation — nullable style
    // -----------------------------------------------------------------------

    #[Test]
    public function it_renders_optional_style_when_configured(): void
    {
        config(['typescript-generator.nullable_style' => 'optional']);
        $generator = new TypeScriptGenerator(config('typescript-generator'));

        $output = $generator->generateForModel(Post::class);

        $this->assertStringContainsString('body?: string;', $output);
        $this->assertStringNotContainsString('body: string | null;', $output);
    }

    // -----------------------------------------------------------------------
    //  Type overrides
    // -----------------------------------------------------------------------

    #[Test]
    public function it_applies_manual_type_overrides(): void
    {
        config(['typescript-generator.type_overrides' => [
            User::class => ['email' => '"user@example.com"'],
        ]]);
        $generator = new TypeScriptGenerator(config('typescript-generator'));

        $output = $generator->generateForModel(User::class);

        $this->assertStringContainsString('email: "user@example.com";', $output);
    }

    // -----------------------------------------------------------------------
    //  Relationships
    // -----------------------------------------------------------------------

    #[Test]
    public function it_excludes_relationships_by_default(): void
    {
        $output = $this->generator->generateForModel(User::class, withRelationships: false);

        $this->assertStringNotContainsString('posts?', $output);
    }

    #[Test]
    public function it_includes_relationships_when_requested(): void
    {
        $output = $this->generator->generateForModel(User::class, withRelationships: true);

        $this->assertStringContainsString('posts?:', $output);
        $this->assertStringContainsString('Post[]', $output);
    }

    // -----------------------------------------------------------------------
    //  Full pipeline — files written
    // -----------------------------------------------------------------------

    #[Test]
    public function it_writes_d_ts_files_to_the_output_directory(): void
    {
        $this->generator->generate();

        $outputDir = config('typescript-generator.output_directory');

        $this->assertFileExists($outputDir . '/User.d.ts');
        $this->assertFileExists($outputDir . '/Post.d.ts');
        $this->assertFileExists($outputDir . '/index.d.ts');
    }

    #[Test]
    public function it_writes_a_barrel_index_that_re_exports_all_models(): void
    {
        $this->generator->generate();

        $index = file_get_contents(config('typescript-generator.output_directory') . '/index.d.ts');

        $this->assertStringContainsString("export type { User } from './User';", $index);
        $this->assertStringContainsString("export type { Post } from './Post';", $index);
    }

    #[Test]
    public function it_returns_generated_model_fqcns_in_results(): void
    {
        $results = $this->generator->generate();

        $this->assertContains(User::class, $results['generated']);
        $this->assertContains(Post::class, $results['generated']);
        $this->assertEmpty($results['errors']);
    }
}
