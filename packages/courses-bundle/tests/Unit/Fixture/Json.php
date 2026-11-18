<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

/**
 * Reads the JSON documents the tests receive: decoded as objects, and walked by keys.
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * @return array<array-key, mixed> the decoded object, empty when the text is not one
     */
    public static function decode(mixed $text): array
    {
        $decoded = \is_string($text) ? json_decode($text, true) : null;

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * The value at that path, or null when a key is missing.
     */
    public static function at(mixed $value, int|string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    public static function text(mixed $value, int|string ...$path): string
    {
        $found = self::at($value, ...$path);

        return \is_string($found) ? $found : '';
    }
}
