<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit\Fixture;

/**
 * A PNG file of one black pixel, for the tests.
 */
final class Png
{
    public static function pixel(): string
    {
        $data = gzcompress("\0\0\0\0");

        return "\x89PNG\r\n\x1a\n".self::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0)).self::chunk('IDAT', false === $data ? '' : $data).self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
