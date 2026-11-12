<?php

declare(strict_types=1);

namespace Phil\ApiContract\TolerantReading;

use BackedEnum;
use Phil\ApiContract\Exception\UnknownAuthorityValue;

/**
 * Reading an enumeration from a contract that grows by addition.
 *
 * A new enumeration value is not a breaking change, so a client meets values it does not know. Two rules,
 * chosen per field:
 * - an ordinary field takes the default the contract sets for it (`withDefault()`);
 * - **a field of authority refuses** (`authoritative()`): an unknown assurance level satisfies no
 *   requirement, an unknown grant state grants nothing, an unknown security event type is not processed.
 *
 * Unknown fields, the other half of tolerant reading, are the deserializer's concern: never validate a
 * response against a closed schema.
 */
final class TolerantEnum
{
    private function __construct()
    {
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     * @param T               $default
     *
     * @return T
     */
    public static function withDefault(string $enum, mixed $value, BackedEnum $default): BackedEnum
    {
        return self::tryFrom($enum, $value) ?? $default;
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     *
     * @throws UnknownAuthorityValue
     */
    public static function authoritative(string $enum, mixed $value): BackedEnum
    {
        return self::tryFrom($enum, $value) ?? throw UnknownAuthorityValue::for($enum, $value);
    }

    /**
     * A JSON value of the wrong type is as unknown as a string the enumeration does not list. No coercion:
     * `"1"` is not `1`.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    private static function tryFrom(string $enum, mixed $value): ?BackedEnum
    {
        foreach ($enum::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }
}
