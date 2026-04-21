<?php

namespace SynergiTech\TypeScriptGenerator\Mappers;

class TypeMapper
{
    /**
     * Map a database column type string to a TypeScript type.
     */
    public static function fromDatabaseType(string $dbType): string
    {
        $dbType = strtolower(trim($dbType));

        // Strip length/precision specifiers: varchar(255) → varchar, decimal(8,2) → decimal
        $baseType = preg_replace('/\(.*\)/', '', $dbType);
        $baseType = trim($baseType);

        // Also strip "unsigned" and similar modifiers
        $baseType = preg_replace('/\s+(unsigned|signed)/', '', $baseType);
        $baseType = trim($baseType);

        return match ($baseType) {
            // Integers
            'int', 'integer', 'tinyint', 'smallint',
            'mediumint', 'bigint', 'int2', 'int4', 'int8',
            'serial', 'bigserial', 'smallserial' => 'number',

            // Floating point
            'float', 'double', 'double precision', 'real',
            'decimal', 'numeric', 'money' => 'number',

            // Boolean
            'boolean', 'bool' => 'boolean',

            // Strings
            'char', 'varchar', 'character varying', 'text',
            'tinytext', 'mediumtext', 'longtext',
            'string', 'uuid', 'ulid', 'guid',
            'nchar', 'nvarchar', 'ntext',
            'citext' => 'string',

            // Date/time — represented as ISO 8601 strings in JSON responses
            'date', 'datetime', 'timestamp', 'timestamptz',
            'timestamp without time zone', 'timestamp with time zone',
            'time', 'timetz', 'year' => 'string',

            // JSON
            'json', 'jsonb' => 'Record<string, unknown>',

            // Binary
            'blob', 'tinyblob', 'mediumblob', 'longblob',
            'binary', 'varbinary', 'bytea' => 'string',

            // Enums — we can't know the values from just the type name,
            // but schema introspection may pass allowed values separately
            'enum', 'set' => 'string',

            // Geometry / spatial
            'geometry', 'point', 'linestring', 'polygon',
            'multipoint', 'multilinestring', 'multipolygon',
            'geometrycollection' => 'Record<string, unknown>',

            // Fallback
            default => 'unknown',
        };
    }

    /**
     * Map a Laravel $casts value to a TypeScript type.
     * Cast values take priority over raw DB column types.
     */
    public static function fromLaravelCast(string $cast): string
    {
        $trimmed = trim($cast);

        // Class-based casts must be checked before lowercasing to preserve case
        if (str_contains($trimmed, '\\')) {
            return self::fromCastClassName($trimmed);
        }

        $cast = strtolower($trimmed);

        return match ($cast) {
            'int', 'integer' => 'number',
            'real', 'float', 'double', 'decimal' => 'number',
            'string', 'encrypted' => 'string',
            'bool', 'boolean' => 'boolean',
            'object' => 'Record<string, unknown>',
            'array', 'json' => 'unknown[]',
            'collection' => 'unknown[]',
            'date', 'datetime', 'custom_datetime',
            'immutable_date', 'immutable_datetime',
            'immutable_custom_datetime', 'timestamp' => 'string',
            'encrypted:array' => 'unknown[]',
            'encrypted:collection' => 'unknown[]',
            'encrypted:object' => 'Record<string, unknown>',
            'hashed' => 'string',
            default => self::handleDynamicCast($cast),
        };
    }

    /**
     * Handle casts with parameters, e.g. "decimal:2"
     */
    protected static function handleDynamicCast(string $cast): string
    {
        if (str_starts_with($cast, 'decimal:')) {
            return 'number';
        }

        if (str_starts_with($cast, 'datetime:') || str_starts_with($cast, 'date:')) {
            return 'string';
        }

        if (str_starts_with($cast, 'immutable_datetime:') || str_starts_with($cast, 'immutable_date:')) {
            return 'string';
        }

        if (str_starts_with($cast, 'encrypted:')) {
            return 'unknown';
        }

        // If it looks like it could be an enum class (backed enums)
        // We can't resolve the values without instantiation, so mark as string
        return 'unknown';
    }

    /**
     * Resolve well-known Laravel cast classes to TypeScript types.
     */
    protected static function fromCastClassName(string $className): string
    {
        // Normalize to just the class basename for matching
        $basename = class_basename($className);

        return match ($basename) {
            'AsArrayObject' => 'Record<string, unknown>',
            'AsCollection' => 'unknown[]',
            'AsStringable' => 'string',
            'AsEncryptedArrayObject' => 'Record<string, unknown>',
            'AsEncryptedCollection' => 'unknown[]',
            'AsEnumArrayObject' => 'unknown[]',
            'AsEnumCollection' => 'unknown[]',
            default => 'unknown',
        };
    }

    /**
     * Map a Laravel relationship method return type to a TypeScript type string.
     *
     * @param  string  $relationshipType  The short class name of the relationship (e.g. "HasMany")
     * @param  string  $relatedTsType     The TypeScript interface name of the related model
     * @return string
     */
    public static function fromRelationship(string $relationshipType, string $relatedTsType): string
    {
        return match ($relationshipType) {
            // Single / nullable relations
            'HasOne', 'BelongsTo', 'MorphOne', 'HasOneThrough' => "{$relatedTsType} | null",

            // Collection relations
            'HasMany', 'BelongsToMany', 'HasManyThrough',
            'MorphMany', 'MorphToMany', 'MorphedByMany' => "{$relatedTsType}[]",

            // Polymorphic single
            'MorphTo' => 'unknown',

            default => 'unknown',
        };
    }
}
