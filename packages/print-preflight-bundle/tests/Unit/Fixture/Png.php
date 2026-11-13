<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit\Fixture;

/**
 * Writes PNG files for the tests: grey or RGB, of the size given, every pixel black.
 */
final class Png
{
    public static function of(int $width, int $height, bool $grey = false): string
    {
        $row = "\0".str_repeat("\0", $grey ? $width : 3 * $width);
        $data = gzcompress(str_repeat($row, $height));

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, $grey ? 0 : 2, 0, 0, 0))
            .self::chunk('IDAT', false === $data ? '' : $data)
            .self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
