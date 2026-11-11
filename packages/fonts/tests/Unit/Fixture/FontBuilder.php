<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit\Fixture;

use RuntimeException;

/**
 * Writes font files byte by byte for the tests: an `sfnt` from its tables, a collection, WOFF and WOFF2 containers,
 * and the tables the library reads.
 */
final class FontBuilder
{
    /** The WOFF2 index of the tags the tests use. */
    private const array WOFF2_INDEX = ['cmap' => 0, 'head' => 1, 'hmtx' => 3, 'name' => 5, 'OS/2' => 6, 'post' => 7, 'glyf' => 10, 'loca' => 11, 'fvar' => 47, 'Sill' => 62];

    /**
     * @param array<string, string> $tables
     */
    public static function sfnt(array $tables, string $version = "\0\1\0\0"): string
    {
        return self::directory($tables, $version, 0);
    }

    /**
     * @param list<array<string, string>> $faces
     */
    public static function collection(array $faces): string
    {
        $header = 'ttcf'.self::u16(2).self::u16(0).self::u32(\count($faces));
        $offset = 12 + 4 * \count($faces);
        $offsets = '';
        $body = '';

        foreach ($faces as $tables) {
            $offsets .= self::u32($offset + \strlen($body));
            $body .= self::directory($tables, "\0\1\0\0", $offset + \strlen($body));
        }

        return $header.$offsets.$body;
    }

    /**
     * @param array<string, string> $tables
     */
    public static function woff(array $tables, bool $compress = true, ?string $metadata = null): string
    {
        $directoryEnd = 44 + 20 * \count($tables);
        $directory = '';
        $body = '';

        foreach ($tables as $tag => $data) {
            $deflated = self::deflate($data);
            $stored = $compress && \strlen($deflated) < \strlen($data) ? $deflated : $data;
            $directory .= $tag.self::u32($directoryEnd + \strlen($body)).self::u32(\strlen($stored)).self::u32(\strlen($data)).self::u32(0);
            $body .= self::padded($stored);
        }

        $metaOffset = $directoryEnd + \strlen($body);
        $meta = null === $metadata ? '' : self::deflate($metadata);
        $header = 'wOFF'."\0\1\0\0".self::u32($metaOffset + \strlen($meta)).self::u16(\count($tables)).self::u16(0)
            .self::u32(0).self::u16(1).self::u16(0)
            .self::u32('' === $meta ? 0 : $metaOffset).self::u32(\strlen($meta)).self::u32(null === $metadata ? 0 : \strlen($metadata))
            .self::u32(0).self::u32(0);

        return $header.$directory.$body.$meta;
    }

    /**
     * A WOFF2 file whose "compressed" stream is the tables as they are, read back with an identity decompressor.
     *
     * @param list<array{0: string, 1: string, 2: int, 3?: bool}> $tables tag, data, transform version, and whether the tag
     *                                                                    is written out rather than given by its index
     */
    public static function woff2(array $tables, ?string $metadata = null, string $flavor = "\0\1\0\0"): string
    {
        $directory = '';
        $stream = '';

        foreach ($tables as $table) {
            [$tag, $data, $version] = $table;
            $index = ($table[3] ?? false) ? 63 : self::WOFF2_INDEX[$tag] ?? 63;
            $transformed = \in_array($tag, ['glyf', 'loca'], true) ? 3 !== $version : 0 !== $version;
            $directory .= \chr(($version << 6 | $index) & 0xFF).(63 === $index ? $tag : '')
                .($transformed ? self::base128(\strlen($data) + 7).self::base128(\strlen($data)) : self::base128(\strlen($data)));
            $stream .= $data;
        }

        $streamOffset = 48 + \strlen($directory);
        $metaOffset = $streamOffset + \strlen($stream);
        $meta = $metadata ?? '';
        $header = 'wOF2'.$flavor.self::u32($metaOffset + \strlen($meta)).self::u16(\count($tables)).self::u16(0)
            .self::u32(0).self::u32(\strlen($stream)).self::u16(1).self::u16(0)
            .self::u32('' === $meta ? 0 : $metaOffset).self::u32(\strlen($meta)).self::u32(\strlen($meta))
            .self::u32(0).self::u32(0);

        return $header.$directory.$stream.$meta;
    }

    /**
     * A name table of Windows US English strings, by name identifier.
     *
     * @param array<int, string> $names
     */
    public static function names(array $names): string
    {
        return self::name(array_map(
            static fn (int $id, string $value): array => [3, 1, 0x0409, $id, mb_convert_encoding($value, 'UTF-16BE', 'UTF-8')],
            array_keys($names),
            $names,
        ));
    }

    /**
     * @param list<array{int, int, int, int, string}> $records platform, encoding, language, name and encoded bytes
     */
    public static function name(array $records): string
    {
        $head = self::u16(0).self::u16(\count($records)).self::u16(6 + 12 * \count($records));
        $storage = '';

        foreach ($records as [$platform, $encoding, $language, $id, $bytes]) {
            $head .= self::u16($platform).self::u16($encoding).self::u16($language).self::u16($id).self::u16(\strlen($bytes)).self::u16(\strlen($storage));
            $storage .= $bytes;
        }

        return $head.$storage;
    }

    public static function os2(int $weight = 400, int $width = 5, int $fsType = 0, int $selection = 0, int $length = 96): string
    {
        $table = self::u16(4).self::i16(500).self::u16($weight).self::u16($width).self::u16($fsType).str_repeat("\0", 52).self::u16($selection);

        return substr(str_pad($table, max($length, \strlen($table)), "\0"), 0, $length);
    }

    public static function head(int $macStyle = 0): string
    {
        return str_repeat("\0", 44).self::u16($macStyle).str_repeat("\0", 8);
    }

    public static function post(int $angle = 0, int $fraction = 0, int $fixedPitch = 0): string
    {
        return self::u32(0x00030000).self::i16($angle).self::u16($fraction).self::i16(-100).self::i16(50).self::u32($fixedPitch).str_repeat("\0", 16);
    }

    /**
     * @param list<array{string, float, float, float, int}> $axes tag, minimum, default, maximum and name identifier
     */
    public static function fvar(array $axes, int $axisSize = 20): string
    {
        $table = self::u16(1).self::u16(0).self::u16(16).self::u16(2).self::u16(\count($axes)).self::u16($axisSize).self::u16(0).self::u16(0);

        foreach ($axes as [$tag, $minimum, $default, $maximum, $nameId]) {
            $table .= str_pad($tag.self::fixed($minimum).self::fixed($default).self::fixed($maximum).self::u16(0).self::u16($nameId), $axisSize, "\0");
        }

        return $table;
    }

    /**
     * @param list<array{int, int, string}> $subtables platform, encoding and subtable bytes
     */
    public static function cmap(array $subtables): string
    {
        $table = self::u16(0).self::u16(\count($subtables));
        $offset = 4 + 8 * \count($subtables);
        $body = '';

        foreach ($subtables as [$platform, $encoding, $bytes]) {
            $table .= self::u16($platform).self::u16($encoding).self::u32($offset + \strlen($body));
            $body .= $bytes;
        }

        return $table.$body;
    }

    /**
     * A format 4 subtable of the segments given, closed by the segment of 0xFFFF; every glyph is 1.
     *
     * @param list<array{int, int}> $segments
     */
    public static function format4(array $segments): string
    {
        $segments[] = [0xFFFF, 0xFFFF];
        $count = \count($segments);
        $ends = implode('', array_map(static fn (array $segment): string => self::u16($segment[1]), $segments));
        $starts = implode('', array_map(static fn (array $segment): string => self::u16($segment[0]), $segments));
        $deltas = implode('', array_map(static fn (array $segment): string => self::u16(1 - $segment[0] & 0xFFFF), $segments));

        return self::u16(4).self::u16(16 + 8 * $count).self::u16(0).self::u16(2 * $count).self::u16(0).self::u16(0).self::u16(0)
            .$ends.self::u16(0).$starts.$deltas.str_repeat("\0\0", $count);
    }

    /**
     * @param list<array{int, int}> $groups
     */
    public static function format12(array $groups, int $language = 0): string
    {
        $table = self::u16(12).self::u16(0).self::u32(16 + 12 * \count($groups)).self::u32($language).self::u32(\count($groups));

        foreach ($groups as [$first, $last]) {
            $table .= self::u32($first).self::u32($last).self::u32(1);
        }

        return $table;
    }

    public static function u16(int $value): string
    {
        return pack('n', $value);
    }

    public static function i16(int $value): string
    {
        return pack('n', $value & 0xFFFF);
    }

    public static function u32(int $value): string
    {
        return pack('N', $value);
    }

    public static function fixed(float $value): string
    {
        return self::u32((int) round($value * 65536) & 0xFFFFFFFF);
    }

    public static function base128(int $value): string
    {
        $bytes = \chr($value & 0x7F);

        for ($value >>= 7; $value > 0; $value >>= 7) {
            $bytes = \chr(0x80 | $value & 0x7F).$bytes;
        }

        return $bytes;
    }

    /**
     * @param array<string, string> $tables
     */
    private static function directory(array $tables, string $version, int $start): string
    {
        $count = \count($tables);
        $offset = $start + 12 + 16 * $count;
        $directory = $version.self::u16($count).self::u16(0).self::u16(0).self::u16(0);
        $body = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.self::u32(0).self::u32($offset + \strlen($body)).self::u32(\strlen($data));
            $body .= self::padded($data);
        }

        return $directory.$body;
    }

    private static function deflate(string $data): string
    {
        $deflated = gzcompress($data);

        return false === $deflated ? throw new RuntimeException('zlib failed.') : $deflated;
    }

    private static function padded(string $data): string
    {
        return $data.str_repeat("\0", (4 - \strlen($data) % 4) % 4);
    }
}
