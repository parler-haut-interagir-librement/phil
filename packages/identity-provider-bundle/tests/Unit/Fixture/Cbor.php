<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use InvalidArgumentException;

/**
 * A CBOR writer for the tests: what an authenticator would send. Integers, byte strings (`Cbor::bytes()`), texts,
 * lists, maps, booleans and null.
 */
final class Cbor
{
    private function __construct()
    {
    }

    /**
     * A byte string, which the writer would otherwise take for a text.
     */
    public static function bytes(string $bytes): object
    {
        return new readonly class($bytes) {
            public function __construct(public string $bytes)
            {
            }
        };
    }

    public static function encode(mixed $value): string
    {
        return match (true) {
            \is_int($value) => $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value),
            \is_string($value) => self::head(3, \strlen($value)).$value,
            \is_bool($value) => $value ? "\xF5" : "\xF4",
            null === $value => "\xF6",
            \is_array($value) && array_is_list($value) => self::head(4, \count($value)).implode('', array_map(self::encode(...), $value)),
            \is_array($value) => self::head(5, \count($value)).implode('', array_map(static fn (int|string $key, mixed $item): string => self::encode($key).self::encode($item), array_keys($value), $value)),
            \is_object($value) && property_exists($value, 'bytes') && \is_string($value->bytes) => self::head(2, \strlen($value->bytes)).$value->bytes,
            default => throw new InvalidArgumentException('Not written.'),
        };
    }

    private static function head(int $major, int $argument): string
    {
        return match (true) {
            $argument < 24 => \chr((($major << 5) | $argument) & 0xFF),
            $argument < 0x100 => \chr((($major << 5) | 24) & 0xFF).\chr($argument & 0xFF),
            $argument < 0x10000 => \chr((($major << 5) | 25) & 0xFF).pack('n', $argument),
            $argument < 0x100000000 => \chr((($major << 5) | 26) & 0xFF).pack('N', $argument),
            default => \chr((($major << 5) | 27) & 0xFF).pack('J', $argument),
        };
    }
}
