<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use Phil\ImportWizard\Exception\InvalidProfile;

/**
 * Internal: reads the fields of a serialised profile, each of the type it must have, and names the path of a field
 * that has not.
 */
final class ProfileData
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key, string $path): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw self::wrong($path.'.'.$key, 'a text');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function optionalString(array $data, string $key, string $path): ?string
    {
        return null === ($data[$key] ?? null) ? null : self::string($data, $key, $path);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key, string $path): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::wrong($path.'.'.$key, 'true or false');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function optionalInt(array $data, string $key, string $path): ?int
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_int($value) ? $value : throw self::wrong($path.'.'.$key, 'a whole number');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key, string $path): array
    {
        $strings = [];

        foreach (self::list($data, $key, $path) as $index => $value) {
            $strings[] = \is_string($value) ? $value : throw self::wrong($path.'.'.$key.'['.$index.']', 'a text');
        }

        return $strings;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    public static function arrays(array $data, string $key, string $path): array
    {
        $arrays = [];

        foreach (self::list($data, $key, $path) as $index => $value) {
            $arrays[] = \is_array($value) ? $value : throw self::wrong($path.'.'.$key.'['.$index.']', 'an object');
        }

        return $arrays;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function list(array $data, string $key, string $path): array
    {
        $value = $data[$key] ?? [];

        return \is_array($value) && array_is_list($value) ? $value : throw self::wrong($path.'.'.$key, 'a list');
    }

    private static function wrong(string $path, string $expected): InvalidProfile
    {
        return InvalidProfile::because(\sprintf('The field "%s" must be %s.', $path, $expected));
    }
}
