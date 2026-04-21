<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SynergiTech\TypeScriptGenerator\Services\ResourceGenerator;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Resources\PostResource;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Resources\UserResource;
use SynergiTech\TypeScriptGenerator\Tests\TestCase;

class ResourceGenerationTest extends TestCase
{
    private ResourceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(ResourceGenerator::class);
    }

    // -----------------------------------------------------------------------
    //  Discovery
    // -----------------------------------------------------------------------

    #[Test]
    public function it_discovers_json_resource_subclasses(): void
    {
        $resources = $this->generator->discoverResources();

        $this->assertContains(UserResource::class, $resources);
        $this->assertContains(PostResource::class, $resources);
    }

    #[Test]
    public function it_returns_resources_sorted_alphabetically(): void
    {
        $resources = $this->generator->discoverResources();

        $sorted = $resources;
        sort($sorted);

        $this->assertSame($sorted, $resources);
    }

    #[Test]
    public function it_skips_excluded_resources(): void
    {
        config(['typescript-generator.excluded_resources' => [UserResource::class]]);
        $generator = new ResourceGenerator(config('typescript-generator'));

        $results = $generator->generate();

        $this->assertNotContains(UserResource::class, $results['generated']);
        $this->assertContains(UserResource::class, $results['skipped']);
    }

    // -----------------------------------------------------------------------
    //  Content — UserResource
    // -----------------------------------------------------------------------

    #[Test]
    public function it_generates_an_interface_for_a_resource(): void
    {
        $output = $this->generator->generateForResource(UserResource::class);

        $this->assertStringContainsString('export interface UserResource {', $output);
    }

    #[Test]
    public function it_includes_scalar_fields(): void
    {
        $output = $this->generator->generateForResource(UserResource::class);

        $this->assertStringContainsString('name:', $output);
        $this->assertStringContainsString('email:', $output);
    }

    #[Test]
    public function it_marks_conditional_fields_as_optional(): void
    {
        $output = $this->generator->generateForResource(UserResource::class);

        $this->assertStringContainsString('secret?:', $output);
    }

    #[Test]
    public function it_does_not_mark_always_present_fields_as_optional(): void
    {
        $output = $this->generator->generateForResource(UserResource::class);

        $this->assertStringNotContainsString('name?:', $output);
        $this->assertStringNotContainsString('email?:', $output);
    }

    // -----------------------------------------------------------------------
    //  Content — PostResource (nested resource)
    // -----------------------------------------------------------------------

    #[Test]
    public function it_adds_an_import_for_nested_resources(): void
    {
        // Generate all resources first so the resourceMap is populated
        $this->generator->generate();

        $output = $this->generator->generateForResource(PostResource::class);

        $this->assertStringContainsString("import type { UserResource } from './UserResource';", $output);
    }

    #[Test]
    public function it_types_nested_resources_by_interface_name(): void
    {
        $this->generator->generate();

        $output = $this->generator->generateForResource(PostResource::class);

        $this->assertStringContainsString('author: UserResource;', $output);
    }

    // -----------------------------------------------------------------------
    //  Full pipeline — files written
    // -----------------------------------------------------------------------

    #[Test]
    public function it_writes_d_ts_files_to_the_output_directory(): void
    {
        $this->generator->generate();

        $outputDir = config('typescript-generator.resource_output_directory');

        $this->assertFileExists($outputDir . '/UserResource.d.ts');
        $this->assertFileExists($outputDir . '/PostResource.d.ts');
        $this->assertFileExists($outputDir . '/index.d.ts');
    }

    #[Test]
    public function it_writes_a_barrel_index_that_re_exports_all_resources(): void
    {
        $this->generator->generate();

        $index = file_get_contents(config('typescript-generator.resource_output_directory') . '/index.d.ts');

        $this->assertStringContainsString("export type { UserResource } from './UserResource';", $index);
        $this->assertStringContainsString("export type { PostResource } from './PostResource';", $index);
    }

    #[Test]
    public function it_returns_generated_resource_fqcns_in_results(): void
    {
        $results = $this->generator->generate();

        $this->assertContains(UserResource::class, $results['generated']);
        $this->assertContains(PostResource::class, $results['generated']);
        $this->assertEmpty($results['errors']);
    }

    #[Test]
    public function it_includes_resource_fqcn_in_header_comment(): void
    {
        $output = $this->generator->generateForResource(UserResource::class);

        $this->assertStringContainsString('// Resource: ' . UserResource::class, $output);
    }
}
