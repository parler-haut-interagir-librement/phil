<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * Unsigned integers read from the bytes of a file, past its ends read as zeros: the writers check every length against
 * the file before they trust it.
 *
 * @internal
 */
final class Bytes
{
    /**
     * A big-endian integer of `$size` bytes, from 0 to 8; one of 8 bytes beyond `PHP_INT_MAX` comes out negative.
     *
     * @param int<0, 8> $size
     */
    public static function bigEndian(string $bytes, int $offset, int $size): int
    {
        $value = 0;

        for ($index = 0; $index < $size; ++$index) {
            $value = $value << 8 | self::byte($bytes, $offset + $index);
        }

        return $value;
    }

    /**
     * A little-endian integer of `$size` bytes, from 0 to 4.
     *
     * @param int<0, 4> $size
     */
    public static function littleEndian(string $bytes, int $offset, int $size): int
    {
        $value = 0;

        for ($index = $size - 1; $index >= 0; --$index) {
            $value = $value << 8 | self::byte($bytes, $offset + $index);
        }

        return $value;
    }

    private static function byte(string $bytes, int $position): int
    {
        return $position < 0 ? 0 : \ord(($bytes[$position] ?? "\0")[0]);
    }
}
