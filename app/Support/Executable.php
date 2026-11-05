<?php

namespace App\Support;

/**
 * Finds external programs (Chrome, Ghostscript) on Linux, macOS and Windows.
 * Candidates are either full paths or command names looked up on the PATH.
 */
class Executable
{
    /** @param  list<string|null>  $candidates */
    public static function find(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (self::isPath($candidate)) {
                if (is_file($candidate) && (self::onWindows() || is_executable($candidate))) {
                    return $candidate;
                }

                continue;
            }
            if ($path = self::onPath($candidate)) {
                return $path;
            }
        }

        return null;
    }

    public static function isPath(string $candidate): bool
    {
        return str_contains($candidate, '/') || str_contains($candidate, '\\');
    }

    public static function onWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /** A file:// URL for a local path, including Windows drive paths (file:///C:/...). */
    public static function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return 'file://'.(str_starts_with($path, '/') ? '' : '/').$path;
    }

    private static function onPath(string $name): ?string
    {
        $command = self::onWindows()
            ? 'where '.escapeshellarg($name).' 2>NUL'
            : 'command -v '.escapeshellarg($name).' 2>/dev/null';
        $first = strtok(trim((string) @shell_exec($command)), "\r\n");

        return $first !== false && $first !== '' ? $first : null;
    }
}
