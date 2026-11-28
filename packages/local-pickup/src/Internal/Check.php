<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Internal;

use Phil\LocalPickup\Exception\InvalidPickup;

/**
 * The bounds of every identifier and text the library keeps.
 *
 * @internal
 */
final class Check
{
    public const int MAX_OPAQUE = 255;

    public const int MAX_TEXT = 500;

    private function __construct()
    {
    }

    /**
     * An order number: up to 64 letters, digits, "_", ".", "#", "/" or "-", starting with a letter or a digit. It never
     * holds ":", which separates it from the code in a scanned payload.
     */
    public static function order(string $value): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.#\/-]{0,63}$/D', $value)) {
            throw InvalidPickup::because('order number', 'is up to 64 letters, digits, "_", ".", "#", "/" or "-", starting with a letter or a digit');
        }

        return $value;
    }

    /**
     * The code of a pickup point or of a stock location: up to 64 lowercase letters, digits, "_", "." or "-".
     */
    public static function code(string $field, string $value): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidPickup::because($field, 'is up to 64 lowercase letters, digits, "_", "." or "-"');
        }

        return $value;
    }

    /**
     * An identifier the library never parses — a customer, a member of the staff, a counter or a register: not empty,
     * at most 255 bytes, without whitespace nor control characters.
     */
    public static function opaque(string $field, string $value): string
    {
        if ('' === $value || \strlen($value) > self::MAX_OPAQUE || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidPickup::because($field, 'is 1 to 255 bytes without whitespace nor control characters');
        }

        return $value;
    }

    /**
     * A text shown to people — a name, an address: valid UTF-8, not blank, at most 500 bytes, without control
     * characters but the line breaks an address keeps when it is multiline.
     */
    public static function text(string $field, string $value, bool $multiline = false): string
    {
        $control = $multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';

        if ('' === trim($value) || \strlen($value) > self::MAX_TEXT || 1 !== preg_match('//u', $value) || 1 === preg_match($control, $value)) {
            throw InvalidPickup::because($field, 'is a text of 1 to 500 bytes of UTF-8 without control characters');
        }

        return $value;
    }

    public static function range(string $field, int $value, int $min, int $max): int
    {
        if ($value < $min || $value > $max) {
            throw InvalidPickup::because($field, \sprintf('is between %d and %d', $min, $max));
        }

        return $value;
    }
}
