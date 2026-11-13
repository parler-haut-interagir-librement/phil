<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit\Fixture;

/**
 * Writes small font files for the tests: a TrueType font with one square glyph for "A", which phil/fonts reads for its
 * metadata and phil/paged-media embeds, and the same tables in WOFF and WOFF2 containers.
 */
final class Fonts
{
    public static function trueType(string $family, string $subfamily = 'Regular', int $weight = 400, int $fsType = 0, bool $italic = false, string $licenceUrl = 'https://openfontlicense.org'): string
    {
        return self::sfnt(self::tables($family, $subfamily, $weight, $fsType, $italic, $licenceUrl));
    }

    /**
     * The same face with PostScript outlines announced, which phil/paged-media refuses.
     */
    public static function openType(string $family, string $subfamily = 'Regular', int $weight = 400, bool $italic = false): string
    {
        return self::sfnt(self::tables($family, $subfamily, $weight, 0, $italic, 'https://openfontlicense.org'), 'OTTO');
    }

    /**
     * A TrueType collection of the faces given, each a family and a subfamily.
     *
     * @param list<array{string, string}> $faces
     */
    public static function collection(array $faces): string
    {
        $start = 12 + 4 * \count($faces);
        $offsets = '';
        $body = '';

        foreach ($faces as [$family, $subfamily]) {
            $offsets .= pack('N', $start + \strlen($body));
            $body .= self::sfnt(self::tables($family, $subfamily, 400, 0, false, ''), "\0\1\0\0", $start + \strlen($body));
        }

        return 'ttcf'.pack('nnN', 1, 0, \count($faces)).$offsets.$body;
    }

    /**
     * A WOFF file whose tables are stored as they are.
     */
    public static function woff(string $family, string $subfamily = 'Regular', int $weight = 400): string
    {
        $tables = self::tables($family, $subfamily, $weight, 0, false, 'https://openfontlicense.org');
        ksort($tables, \SORT_STRING);
        $offset = 44 + 20 * \count($tables);
        $directory = '';
        $body = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.pack('NNNN', $offset + \strlen($body), \strlen($data), \strlen($data), 0);
            $body .= self::padded($data);
        }

        return 'wOFF'."\0\1\0\0".pack('Nnn', $offset + \strlen($body), \count($tables), 0).pack('NnnNNNNN', 0, 1, 0, 0, 0, 0, 0, 0).$directory.$body;
    }

    /**
     * A WOFF2 file whose stream holds the tables as they are, read back with `str_pad` as the decompressor; the outlines
     * are left out.
     */
    public static function woff2(string $family, string $subfamily = 'Regular', int $weight = 400): string
    {
        $tables = self::tables($family, $subfamily, $weight, 0, false, 'https://openfontlicense.org');
        unset($tables['glyf'], $tables['loca']);
        $directory = '';
        $stream = '';

        foreach ($tables as $tag => $data) {
            $directory .= \chr(63).$tag.self::base128(\strlen($data));
            $stream .= $data;
        }

        $end = 48 + \strlen($directory) + \strlen($stream);

        return 'wOF2'."\0\1\0\0".pack('NnnNNnn', $end, \count($tables), 0, 0, \strlen($stream), 1, 0).pack('NNNNN', 0, 0, 0, 0, 0).$directory.$stream;
    }

    /**
     * @return array<string, string>
     */
    private static function tables(string $family, string $subfamily, int $weight, int $fsType, bool $italic, string $licenceUrl): array
    {
        $square = pack('nnnnn', 1, 0, 0, 10, 10).pack('nn', 3, 0).str_repeat(\chr(0x01), 4).pack('nnnn', 0, 10, 0, 0x10000 - 10).pack('nnnn', 0, 0, 10, 0);
        $segments = pack('nn', 0x41, 0xFFFF).pack('n', 0).pack('nn', 0x41, 0xFFFF).pack('nn', (1 - 0x41) & 0xFFFF, 1).pack('nn', 0, 0);
        $format4 = pack('nnn', 4, 14 + \strlen($segments), 0).pack('nnnn', 4, 4, 1, 0).$segments;

        return [
            'head' => pack('NNNNnn', 0x00010000, 0x00010000, 0, 0x5F0F3CF5, 0x000B, 1000).str_repeat("\0", 16).pack('nnnn', 0, 0, 10, 10).pack('nnnnn', $italic ? 2 : 0, 8, 2, 0, 0),
            'hhea' => pack('N', 0x00010000).pack('n*', 800, 0x10000 - 200, 0, 1200, 0, 0, 1000, 1, 0, 0, 0, 0, 0, 0, 0).pack('n', 2),
            'maxp' => pack('Nn', 0x00005000, 2),
            'hmtx' => pack('nnnn', 500, 0, 600, 0),
            'cmap' => pack('nn', 0, 1).pack('nnN', 3, 1, 12).$format4,
            'loca' => pack('nnn', 0, 0, intdiv(\strlen($square), 2)),
            'glyf' => $square,
            'name' => self::names([1 => $family, 2 => $subfamily, 4 => trim($family.' '.$subfamily), 6 => str_replace(' ', '', $family).'-'.$subfamily, 14 => $licenceUrl]),
            'OS/2' => pack('nnnnn', 4, 500, $weight, 5, $fsType).str_repeat("\0", 52).pack('n', $italic ? 0x0001 : 0x0040).str_repeat("\0", 22).pack('nn', 500, 700).str_repeat("\0", 6),
            'post' => pack('Nnn', 0x00030000, 0, 0).pack('nnN', 0, 0, 0).str_repeat("\0", 16),
        ];
    }

    /**
     * @param array<int, string> $names
     */
    private static function names(array $names): string
    {
        $records = '';
        $storage = '';

        foreach ($names as $id => $value) {
            $text = mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
            $records .= pack('nnnnnn', 3, 1, 0x409, $id, \strlen($text), \strlen($storage));
            $storage .= $text;
        }

        return pack('nnn', 0, \count($names), 6 + 12 * \count($names)).$records.$storage;
    }

    /**
     * @param array<string, string> $tables
     */
    private static function sfnt(array $tables, string $version = "\0\1\0\0", int $start = 0): string
    {
        ksort($tables, \SORT_STRING);
        $offset = $start + 12 + 16 * \count($tables);
        $directory = '';
        $body = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.pack('NNN', 0, $offset + \strlen($body), \strlen($data));
            $body .= self::padded($data);
        }

        return $version.pack('nnnn', \count($tables), 0, 0, 0).$directory.$body;
    }

    private static function padded(string $data): string
    {
        return $data.str_repeat("\0", (4 - \strlen($data) % 4) % 4);
    }

    private static function base128(int $value): string
    {
        $bytes = \chr($value & 0x7F);

        for ($value >>= 7; $value > 0; $value >>= 7) {
            $bytes = \chr(0x80 | $value & 0x7F).$bytes;
        }

        return $bytes;
    }
}
