<?php

declare(strict_types=1);

namespace Phil\Fonts\Licence;

use Phil\Fonts\Exception\InvalidLicenceRegister;

/**
 * The typed fields of a licence record read back from JSON.
 *
 * @internal
 */
final readonly class Field
{
    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : throw self::invalid($key, 'a string');
    }

    /**
     * @param array<mixed> $data
     */
    public static function optionalString(array $data, string $key): ?string
    {
        if (!\array_key_exists($key, $data)) {
            throw self::invalid($key, 'a string or null');
        }

        return null === $data[$key] ? null : self::string($data, $key);
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return \is_bool($data[$key] ?? null) ? $data[$key] : throw self::invalid($key, 'a boolean');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        return \is_array($data[$key] ?? null) && array_is_list($data[$key]) ? $data[$key] : throw self::invalid($key, 'a list');
    }

    private static function invalid(string $key, string $type): InvalidLicenceRegister
    {
        return InvalidLicenceRegister::because(\sprintf('The field "%s" must be %s.', $key, $type));
    }
}
