<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Internal;

use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use Phil\ProductBundles\Exception\InvalidBundle;

/**
 * The bounds of every identifier and number the library keeps.
 *
 * @internal
 */
final class Check
{
    public const int MAX_COMPONENTS = 50;

    public const int MAX_QUANTITY = 1000;

    public const int MAX_PARTICIPANTS = 50;

    public const int MAX_WEIGHT = 10_000;

    private function __construct()
    {
    }

    /**
     * A SKU: up to 64 letters, digits, "_", ".", "/" or "-", starting with a letter or a digit.
     */
    public static function sku(string $field, string $value): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.\/-]{0,63}$/D', $value)) {
            throw InvalidBundle::because($field, 'is up to 64 letters, digits, "_", ".", "/" or "-", starting with a letter or a digit');
        }

        return $value;
    }

    /**
     * An identifier the library never parses — a seller, a creator, a shop: up to 255 printable characters.
     */
    public static function opaque(string $field, string $value): string
    {
        if ('' === $value || \strlen($value) > 255 || 1 !== preg_match('/^[^\x00-\x1F\x7F]+$/Du', $value)) {
            throw InvalidBundle::because($field, 'is from 1 to 255 printable characters');
        }

        return $value;
    }

    public static function between(string $field, int $value, int $minimum, int $maximum): int
    {
        if ($value < $minimum || $value > $maximum) {
            throw InvalidBundle::because($field, \sprintf('is from %d to %d, not %d', $minimum, $maximum, $value));
        }

        return $value;
    }

    /**
     * A field of a stored row, of the type the row needs.
     *
     * @param array<array-key, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidBundle::because('stored field "'.$key.'"', 'is a string');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidBundle::because('stored field "'.$key.'"', 'is an integer');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function nullableInt(array $row, string $key): ?int
    {
        return null === ($row[$key] ?? null) ? null : self::int($row, $key);
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return list<mixed>
     */
    public static function list(array $row, string $key, int $maximum): array
    {
        $value = $row[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value) || \count($value) > $maximum) {
            throw InvalidBundle::because('stored field "'.$key.'"', \sprintf('is a list of at most %d entries', $maximum));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function row(mixed $value, string $field): array
    {
        if (!\is_array($value)) {
            throw InvalidBundle::because($field, 'is an array');
        }

        return $value;
    }

    public static function currency(string $code): Currency
    {
        try {
            return Currency::of($code);
        } catch (MoneyException) {
            throw InvalidBundle::because('currency', 'is a code of ISO 4217');
        }
    }

    /**
     * An amount of a stored row, in the currency the row names.
     *
     * @param array<array-key, mixed> $row
     */
    public static function money(array $row, string $key, Currency $currency): Money
    {
        try {
            return Money::of(self::int($row, $key), $currency);
        } catch (MoneyException) {
            throw InvalidBundle::because('stored field "'.$key.'"', 'is an amount');
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function nullableMoney(array $row, string $key, Currency $currency): ?Money
    {
        return null === ($row[$key] ?? null) ? null : self::money($row, $key, $currency);
    }
}
