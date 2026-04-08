<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where your Eloquent models live. The generator will scan
    | this namespace and attempt to resolve each class to generate types.
    |
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Model Directory
    |--------------------------------------------------------------------------
    |
    | The directory path (relative to base_path()) that corresponds to the
    | model namespace above. Used to discover model files on disk.
    |
    */
    'model_directory' => 'app/Models',

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Where the generated .d.ts files will be written to, relative to
    | base_path(). Each model gets its own file.
    |
    */
    'output_directory' => 'resources/js/types',

    /*
    |--------------------------------------------------------------------------
    | Include Relationships
    |--------------------------------------------------------------------------
    |
    | Whether to include relationship properties in the generated types by
    | default. This can be overridden at runtime with --with-relationships
    | or --without-relationships flags on the artisan command.
    |
    */
    'include_relationships' => false,

    /*
    |--------------------------------------------------------------------------
    | Include Timestamps
    |--------------------------------------------------------------------------
    |
    | Whether to include created_at / updated_at even if the model has
    | $timestamps = false. When true, timestamps are always included.
    | When false, the generator respects the model's $timestamps property.
    |
    */
    'include_timestamps' => false,

    /*
    |--------------------------------------------------------------------------
    | Nullable Suffix
    |--------------------------------------------------------------------------
    |
    | How nullable columns should be represented in TypeScript.
    | 'union'  → field: string | null
    | 'optional' → field?: string
    |
    */
    'nullable_style' => 'union',

    /*
    |--------------------------------------------------------------------------
    | Excluded Models
    |--------------------------------------------------------------------------
    |
    | Fully qualified class names of models that should be skipped during
    | generation. Useful for pivot models or other internal models.
    |
    */
    'excluded_models' => [
        // 'App\\Models\\Pivot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Type Overrides
    |--------------------------------------------------------------------------
    |
    | Manual overrides for specific model fields. Keyed by the fully qualified
    | model class, then by column name. The value is the raw TypeScript type
    | string that will be used verbatim.
    |
    | Example:
    |   'App\\Models\\User' => [
    |       'metadata' => '{ avatar: string; theme: "light" | "dark" }',
    |   ],
    |
    */
    'type_overrides' => [],

];
