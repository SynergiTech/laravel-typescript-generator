<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SynergiTech\TypeScriptGenerator\Services\TypeScriptGenerator;
use SynergiTech\TypeScriptGenerator\Tests\Fixtures\Enums\Status;
use SynergiTech\TypeScriptGenerator\Tests\TestCase;

class EnumGenerationTest extends TestCase
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
    public function it_discovers_backed_enums(): void
    {
        $enums = $this->generator->discoverEnums();

        $this->assertContains(Status::class, $enums);
    }

    // -----------------------------------------------------------------------
    //  Content
    // -----------------------------------------------------------------------

    #[Test]
    public function it_generates_a_typescript_enum_block(): void
    {
        $output = $this->generator->generateForEnum(Status::class);

        $this->assertStringContainsString('export enum Status {', $output);
    }

    #[Test]
    public function it_includes_all_cases_with_string_values(): void
    {
        $output = $this->generator->generateForEnum(Status::class);

        $this->assertStringContainsString("Active = 'active',", $output);
        $this->assertStringContainsString("Inactive = 'inactive',", $output);
    }

    #[Test]
    public function it_includes_the_enum_fqcn_in_the_header_comment(): void
    {
        $output = $this->generator->generateForEnum(Status::class);

        $this->assertStringContainsString('// Enum: ' . Status::class, $output);
    }

    // -----------------------------------------------------------------------
    //  Full pipeline
    // -----------------------------------------------------------------------

    #[Test]
    public function it_writes_enum_ts_files_to_the_output_directory(): void
    {
        $this->generator->generateEnums();

        $outputDir = config('typescript-generator.enum_output_directory');

        $this->assertFileExists($outputDir . '/Status.ts');
        $this->assertFileExists($outputDir . '/index.ts');
    }

    #[Test]
    public function it_writes_a_barrel_index_that_exports_all_enums(): void
    {
        $this->generator->generateEnums();

        $index = file_get_contents(config('typescript-generator.enum_output_directory') . '/index.ts');

        $this->assertStringContainsString("export { Status } from './Status';", $index);
    }

    #[Test]
    public function it_resolves_enum_casts_to_their_typescript_name(): void
    {
        // generate() runs enums first, populating the enumMap used during model generation
        $this->generator->generate();

        $modelOutput = file_get_contents(config('typescript-generator.output_directory') . '/User.d.ts');

        $this->assertStringContainsString('status: Status', $modelOutput);
        $this->assertStringContainsString("import type { Status } from '../enums/Status';", $modelOutput);
    }
}
