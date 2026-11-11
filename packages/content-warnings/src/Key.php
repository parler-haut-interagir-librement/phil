<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * The rule every theme and category key follows: lowercase ASCII letters and digits in words joined by single
 * hyphens, 64 characters at most — `gore`, `self-harm`. A key is stable: it is what stored warnings and preferences
 * name, while labels change freely.
 */
final class Key
{
    public const int MAX_LENGTH = 64;

    public static function isValid(string $key): bool
    {
        return \strlen($key) <= self::MAX_LENGTH && 1 === preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key);
    }
}
