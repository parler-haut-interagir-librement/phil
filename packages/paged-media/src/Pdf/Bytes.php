<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

/**
 * Byte helpers for the writer: hexadecimal strings and big-endian integers.
 *
 * @internal
 */
final class Bytes
{
    /**
     * The bytes as upper-case hexadecimal digits.
     */
    public static function hex(string $bytes): string
    {
        /** @var array{1: string} $digits */
        $digits = unpack('H*', $bytes);

        return strtoupper($digits[1]);
    }

    public static function uint16(int $value): string
    {
        return pack('n', $value);
    }

    /**
     * The unsigned 16-bit big-endian integer at an offset the caller has checked.
     */
    public static function readUint16(string $bytes, int $at): int
    {
        return \ord($bytes[$at]) << 8 | \ord($bytes[$at + 1]);
    }

    /**
     * The unsigned 32-bit big-endian integer at an offset the caller has checked.
     */
    public static function readUint32(string $bytes, int $at): int
    {
        return self::readUint16($bytes, $at) << 16 | self::readUint16($bytes, $at + 2);
    }

    public static function uint32(int $value): string
    {
        return pack('N', $value);
    }
}
