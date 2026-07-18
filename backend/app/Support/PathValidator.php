<?php

namespace App\Support;

class PathValidator
{
    private const SEGMENT_PATTERN = '/^[\w.\- ]+$/';

    /**
     * A relative project path is valid when every "/"-separated segment is a
     * safe, non-traversal name — this is what keeps uploads/moves/renames
     * from escaping the project's files directory.
     */
    public static function isValid(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }

            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                return false;
            }
        }

        return true;
    }
}
