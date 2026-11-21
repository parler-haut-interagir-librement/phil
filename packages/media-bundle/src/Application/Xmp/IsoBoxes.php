<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Xmp;

/**
 * The boxes of an ISO base media file — HEIF, AVIF — and the unsigned integers of their fields.
 *
 * @internal
 */
final class IsoBoxes
{
    /**
     * The boxes between two offsets, each as its type, start, payload start and end; `null` when they do not fill the
     * range exactly or one is sized to the end of the file.
     *
     * @return list<array{string, int, int, int}>|null
     */
    public static function read(string $image, int $from, int $to): ?array
    {
        $boxes = [];
        $offset = $from;

        while ($offset < $to) {
            $size = Bytes::bigEndian($image, $offset, 4);
            $header = 8;

            if (1 === $size) {
                $size = self::uint($image, $offset + 8, 8, $to);
                $header = 16;
            }

            if (null === $size || $size < $header || $size > $to - $offset) {
                return null;
            }

            $boxes[] = [substr($image, $offset + 4, 4), $offset, $offset + $header, $offset + $size];
            $offset += $size;
        }

        return $boxes;
    }

    /**
     * An unsigned integer of 0, 2, 4 or 8 bytes, big-endian; `null` past the end, for another size, or for 8 bytes
     * beyond `PHP_INT_MAX`.
     */
    public static function uint(string $image, int $offset, int $size, int $end): ?int
    {
        if ($offset + $size > $end) {
            return null;
        }

        if (!\in_array($size, [0, 2, 4, 8], true)) {
            return null;
        }

        $value = Bytes::bigEndian($image, $offset, $size);

        return $value < 0 ? null : $value;
    }

    /**
     * An unsigned integer of 0, 4 or 8 bytes, big-endian, as `uint()` reads it.
     */
    public static function bytes(int $value, int $size): string
    {
        return match ($size) {
            0 => '',
            4 => pack('N', $value),
            default => pack('J', $value),
        };
    }
}
