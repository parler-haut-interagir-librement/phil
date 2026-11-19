<?php

declare(strict_types=1);

namespace Phil\EventWeather\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Reading decoded JSON or XML text without trusting its shape: a missing key or a value of another type is null.
 * Not part of the public API.
 *
 * @internal
 */
final class Data
{
    private function __construct()
    {
    }

    /**
     * The value under a path of keys; null when one is missing.
     */
    public static function at(mixed $data, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return \is_array($value) && array_is_list($value) ? $value : [];
    }

    public static function float(mixed $value): ?float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : (\is_string($value) && is_numeric($value) ? (float) $value : null);
    }

    public static function int(mixed $value): ?int
    {
        $float = self::float($value);

        return null === $float || !is_finite($float) ? null : (int) round($float);
    }

    public static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }

    /**
     * An instant written as RFC 3339 / ISO 8601 with its offset, `2026-11-13T12:00:00Z` or
     * `2026-11-13T07:00:00.250-05:00`, in UTC; null otherwise.
     */
    public static function instant(mixed $value): ?DateTimeImmutable
    {
        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,9})?)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value)->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }
    }
}
