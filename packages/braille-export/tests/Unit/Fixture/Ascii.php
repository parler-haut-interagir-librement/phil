<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Fixture;

use InvalidArgumentException;

/**
 * Unicode braille read as North American ASCII braille, so that a test writes the cells it expects as text.
 */
final class Ascii
{
    private const string ASCII = ' A1B\'K2L@CIF/MSP"E3H9O6R^DJG>NTQ,*5<-U8V.%[$+X!&;:4\\0Z7(_?W]#Y)=';

    public static function cell(string $ascii): string
    {
        $mask = strpos(self::ASCII, $ascii);

        if (false === $mask) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a cell of ASCII braille.', $ascii));
        }

        return mb_chr(0x2800 + $mask, 'UTF-8');
    }

    public static function braille(string $ascii): string
    {
        return implode('', array_map(self::cell(...), str_split($ascii)));
    }

    public static function of(string $braille): string
    {
        $ascii = '';

        foreach (mb_str_split($braille, 1, 'UTF-8') as $cell) {
            $mask = mb_ord($cell, 'UTF-8') - 0x2800;
            $ascii .= $mask >= 0 && $mask < 64 ? self::ASCII[$mask] : '?';
        }

        return $ascii;
    }

    /**
     * @param list<string> $rows
     *
     * @return list<string>
     */
    public static function rows(array $rows): array
    {
        return array_map(self::of(...), $rows);
    }
}
