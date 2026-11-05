<?php

namespace SynergiTech\TypeScriptGenerator\Services;

use Illuminate\Filesystem\Filesystem;

final class GeneratedFileWriter
{
    private const TIMESTAMP_PATTERN = '/^\/\/ Generated at:.*$/m';

    public static function putIfChanged(Filesystem $files, string $path, string $contents): void
    {
        if ($files->exists($path)) {
            $existingContents = $files->get($path);

            if (self::withoutTimestamp($existingContents) === self::withoutTimestamp($contents)) {
                return;
            }
        }

        $files->put($path, $contents);
    }

    private static function withoutTimestamp(string $contents): string
    {
        return preg_replace(self::TIMESTAMP_PATTERN, '// Generated at: <timestamp>', $contents) ?? $contents;
    }
}
