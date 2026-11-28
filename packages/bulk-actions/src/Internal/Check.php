<?php

declare(strict_types=1);

namespace Phil\BulkActions\Internal;

use Phil\BulkActions\Exception\InvalidInput;

/**
 * @internal the checks shared by the value objects of the library
 */
final class Check
{
    /** The longest identifier of an item, in bytes: a UUID, a ULID or a composite key fit. */
    public const int MAX_IDENTIFIER = 191;

    /** The longest name of a target, an action, a field, a filter or a filter parameter, in bytes. */
    public const int MAX_NAME = 64;

    /** The longest string value of a change or a filter parameter, in bytes. */
    public const int MAX_STRING = 65_535;

    /** The most values in a list of a change or a filter parameter. */
    public const int MAX_LIST = 1_000;

    /**
     * An item's identifier: 1 to 191 bytes, no control character; an integer is read as its decimal string.
     */
    public static function identifier(string $field, int|string $value): string
    {
        $value = (string) $value;

        if ('' === $value || \strlen($value) > self::MAX_IDENTIFIER || self::hasControl($value)) {
            throw InvalidInput::because($field, \sprintf('must have 1 to %d bytes and no control character', self::MAX_IDENTIFIER));
        }

        return $value;
    }

    /**
     * A name chosen by the code, never by the person: a letter, then letters, digits, "_", "-" or ".".
     */
    public static function name(string $field, string $value): string
    {
        if (\strlen($value) > self::MAX_NAME || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $value)) {
            throw InvalidInput::because($field, \sprintf('must be a letter followed by letters, digits, "_", "-" or ".", %d bytes at most', self::MAX_NAME));
        }

        return $value;
    }

    /**
     * A value a change sets or a filter compares with: a scalar, null, or a list of them, stored and sent as JSON.
     *
     * @return scalar|list<scalar|null>|null
     */
    public static function value(string $field, mixed $value): bool|float|int|string|array|null
    {
        if (\is_array($value)) {
            if (!array_is_list($value) || \count($value) > self::MAX_LIST) {
                throw InvalidInput::because($field, \sprintf('must be a list of %d values at most', self::MAX_LIST));
            }

            $list = [];

            foreach ($value as $item) {
                $list[] = self::scalar($field, $item);
            }

            return $list;
        }

        return self::scalar($field, $value);
    }

    public static function between(string $field, int $value, int $min, int $max): int
    {
        if ($value < $min || $value > $max) {
            throw InvalidInput::because($field, \sprintf('must be a whole number from %d to %d', $min, $max));
        }

        return $value;
    }

    private static function scalar(string $field, mixed $value): bool|float|int|string|null
    {
        if (\is_string($value)) {
            if (\strlen($value) > self::MAX_STRING || 1 !== preg_match('//u', $value)) {
                throw InvalidInput::because($field, \sprintf('must be valid UTF-8 of %d bytes at most', self::MAX_STRING));
            }

            return $value;
        }

        if (\is_float($value) && !is_finite($value)) {
            throw InvalidInput::because($field, 'must be a finite number');
        }

        if (null === $value || \is_bool($value) || \is_int($value) || \is_float($value)) {
            return $value;
        }

        throw InvalidInput::because($field, 'must be a string, a number, a boolean, null or a list of them');
    }

    private static function hasControl(string $value): bool
    {
        return 1 === preg_match('/[\x00-\x1F\x7F]/', $value);
    }
}
