<?php

namespace SynergiTech\TypeScriptGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SynergiTech\TypeScriptGenerator\Mappers\TypeMapper;

class TypeMapperTest extends TestCase
{
    // -----------------------------------------------------------------------
    //  fromDatabaseType
    // -----------------------------------------------------------------------

    #[Test]
    public function it_maps_integer_types_to_number(): void
    {
        foreach (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'int2', 'int4', 'int8'] as $type) {
            $this->assertSame('number', TypeMapper::fromDatabaseType($type), "Failed for: {$type}");
        }
    }

    #[Test]
    public function it_maps_serial_types_to_number(): void
    {
        foreach (['serial', 'bigserial', 'smallserial'] as $type) {
            $this->assertSame('number', TypeMapper::fromDatabaseType($type), "Failed for: {$type}");
        }
    }

    #[Test]
    public function it_maps_float_types_to_number(): void
    {
        foreach (['float', 'double', 'real', 'decimal', 'numeric', 'money'] as $type) {
            $this->assertSame('number', TypeMapper::fromDatabaseType($type), "Failed for: {$type}");
        }
    }

    #[Test]
    public function it_maps_boolean_to_boolean(): void
    {
        $this->assertSame('boolean', TypeMapper::fromDatabaseType('boolean'));
        $this->assertSame('boolean', TypeMapper::fromDatabaseType('bool'));
    }

    #[Test]
    public function it_maps_string_types_to_string(): void
    {
        foreach (['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'uuid', 'ulid'] as $type) {
            $this->assertSame('string', TypeMapper::fromDatabaseType($type), "Failed for: {$type}");
        }
    }

    #[Test]
    public function it_maps_datetime_types_to_string(): void
    {
        foreach (['date', 'datetime', 'timestamp', 'timestamptz', 'time', 'year'] as $type) {
            $this->assertSame('string', TypeMapper::fromDatabaseType($type), "Failed for: {$type}");
        }
    }

    #[Test]
    public function it_maps_json_types_to_record(): void
    {
        $this->assertSame('Record<string, unknown>', TypeMapper::fromDatabaseType('json'));
        $this->assertSame('Record<string, unknown>', TypeMapper::fromDatabaseType('jsonb'));
    }

    #[Test]
    public function it_maps_unknown_types_to_unknown(): void
    {
        $this->assertSame('unknown', TypeMapper::fromDatabaseType('some_custom_type'));
    }

    #[Test]
    public function it_strips_length_specifiers(): void
    {
        $this->assertSame('string', TypeMapper::fromDatabaseType('varchar(255)'));
        $this->assertSame('number', TypeMapper::fromDatabaseType('decimal(8,2)'));
        $this->assertSame('number', TypeMapper::fromDatabaseType('int(11)'));
    }

    #[Test]
    public function it_is_case_insensitive(): void
    {
        $this->assertSame('number', TypeMapper::fromDatabaseType('INT'));
        $this->assertSame('string', TypeMapper::fromDatabaseType('VARCHAR(255)'));
        $this->assertSame('boolean', TypeMapper::fromDatabaseType('BOOLEAN'));
    }

    // -----------------------------------------------------------------------
    //  fromLaravelCast
    // -----------------------------------------------------------------------

    #[Test]
    public function it_maps_integer_casts_to_number(): void
    {
        $this->assertSame('number', TypeMapper::fromLaravelCast('int'));
        $this->assertSame('number', TypeMapper::fromLaravelCast('integer'));
        $this->assertSame('number', TypeMapper::fromLaravelCast('float'));
        $this->assertSame('number', TypeMapper::fromLaravelCast('double'));
        $this->assertSame('number', TypeMapper::fromLaravelCast('decimal'));
        $this->assertSame('number', TypeMapper::fromLaravelCast('decimal:2'));
    }

    #[Test]
    public function it_maps_string_casts_to_string(): void
    {
        $this->assertSame('string', TypeMapper::fromLaravelCast('string'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('encrypted'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('hashed'));
    }

    #[Test]
    public function it_maps_boolean_cast_to_boolean(): void
    {
        $this->assertSame('boolean', TypeMapper::fromLaravelCast('bool'));
        $this->assertSame('boolean', TypeMapper::fromLaravelCast('boolean'));
    }

    #[Test]
    public function it_maps_array_and_json_casts_to_array(): void
    {
        $this->assertSame('unknown[]', TypeMapper::fromLaravelCast('array'));
        $this->assertSame('unknown[]', TypeMapper::fromLaravelCast('json'));
        $this->assertSame('unknown[]', TypeMapper::fromLaravelCast('collection'));
    }

    #[Test]
    public function it_maps_object_cast_to_record(): void
    {
        $this->assertSame('Record<string, unknown>', TypeMapper::fromLaravelCast('object'));
    }

    #[Test]
    public function it_maps_datetime_casts_to_string(): void
    {
        $this->assertSame('string', TypeMapper::fromLaravelCast('date'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('datetime'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('datetime:Y-m-d'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('immutable_date'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('immutable_datetime'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('timestamp'));
    }

    #[Test]
    public function it_maps_well_known_cast_classes(): void
    {
        $this->assertSame('Record<string, unknown>', TypeMapper::fromLaravelCast('Illuminate\\Database\\Eloquent\\Casts\\AsArrayObject'));
        $this->assertSame('unknown[]', TypeMapper::fromLaravelCast('Illuminate\\Database\\Eloquent\\Casts\\AsCollection'));
        $this->assertSame('string', TypeMapper::fromLaravelCast('Illuminate\\Database\\Eloquent\\Casts\\AsStringable'));
    }

    // -----------------------------------------------------------------------
    //  fromRelationship
    // -----------------------------------------------------------------------

    #[Test]
    public function it_maps_single_relations_to_nullable_type(): void
    {
        $this->assertSame('User | null', TypeMapper::fromRelationship('HasOne', 'User'));
        $this->assertSame('User | null', TypeMapper::fromRelationship('BelongsTo', 'User'));
        $this->assertSame('User | null', TypeMapper::fromRelationship('MorphOne', 'User'));
        $this->assertSame('User | null', TypeMapper::fromRelationship('HasOneThrough', 'User'));
    }

    #[Test]
    public function it_maps_collection_relations_to_arrays(): void
    {
        $this->assertSame('Post[]', TypeMapper::fromRelationship('HasMany', 'Post'));
        $this->assertSame('Tag[]', TypeMapper::fromRelationship('BelongsToMany', 'Tag'));
        $this->assertSame('Post[]', TypeMapper::fromRelationship('HasManyThrough', 'Post'));
        $this->assertSame('Comment[]', TypeMapper::fromRelationship('MorphMany', 'Comment'));
        $this->assertSame('Tag[]', TypeMapper::fromRelationship('MorphToMany', 'Tag'));
    }

    #[Test]
    public function it_maps_morph_to_to_unknown(): void
    {
        $this->assertSame('unknown', TypeMapper::fromRelationship('MorphTo', 'Model'));
    }

    #[Test]
    public function it_falls_back_to_unknown_for_unrecognised_relationship(): void
    {
        $this->assertSame('unknown', TypeMapper::fromRelationship('HasOneOrMany', 'Post'));
    }
}
