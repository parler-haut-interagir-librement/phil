<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Internal;

use Phil\InventoryAlerts\Exception\InvalidInput;

/**
 * @internal the checks shared by the value objects of the library
 */
final class Check
{
    /** The largest quantity of units the library computes with: far above any stock, far below an integer overflow. */
    public const float MAX_UNITS = 1.0e12;

    /** The longest lead time, in days. */
    public const float MAX_DAYS = 3_650.0;

    /**
     * A code — a SKU, a location, a supplier: 1 to 128 bytes, no control character.
     */
    public static function code(string $field, string $value): string
    {
        if ('' === $value || \strlen($value) > 128 || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidInput::because($field, 'must have 1 to 128 bytes and no control character');
        }

        return $value;
    }

    public static function quantity(string $field, float $value, float $max = self::MAX_UNITS): float
    {
        if (!is_finite($value) || $value < 0.0 || $value > $max) {
            throw InvalidInput::because($field, 'must be a finite number from 0 to '.self::number($max));
        }

        return $value;
    }

    public static function positive(string $field, float $value, float $max = self::MAX_UNITS): float
    {
        if (!is_finite($value) || $value <= 0.0 || $value > $max) {
            throw InvalidInput::because($field, 'must be a finite number above 0 and at most '.self::number($max));
        }

        return $value;
    }

    public static function units(string $field, int $value, int $min = 0): int
    {
        if ($value < $min || $value > (int) self::MAX_UNITS) {
            throw InvalidInput::because($field, \sprintf('must be a whole number from %d to %s', $min, self::number(self::MAX_UNITS)));
        }

        return $value;
    }

    /**
     * The whole units covering a computed quantity: a quantity that is whole but for the rounding of floats is not
     * raised by one.
     */
    public static function ceil(float $value): int
    {
        return (int) ceil(round($value, 6));
    }

    private static function number(float $value): string
    {
        return number_format($value, 0, '.', ',');
    }
}
