<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Support;

use RuntimeException;

/**
 * A TrueType font made for the tests, written by the tests themselves: each character a filled box as wide as its
 * advance less a margin, on 1,000 units to the em. It covers printable ASCII, `é`, the guillemets `«` and `»`, and the
 * ellipsis `…` — wider than any letter, so that a line cut for it loses more than one — which the fixture font lacks.
 * A character it does not cover draws the box of `.notdef`.
 */
final class BoxFont
{
    public const string FAMILY = 'PhilBoxTest';

    /** The advance of each character not listed in WIDTHS. */
    public const int DEFAULT_WIDTH = 500;

    /** @var array<string, int> */
    public const array WIDTHS = [' ' => 250, 'i' => 250, 'l' => 250, 'm' => 800, 'w' => 800, '«' => 600, '»' => 600, '…' => 1000];

    private const int UNITS = 1000;

    private const int MARGIN = 50;

    private const int TOP = 700;

    /**
     * Writes the font in the directory, once, and gives its path.
     */
    public static function write(string $directory): string
    {
        $path = $directory.'/box-font.ttf';

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(\sprintf('The directory "%s" cannot be made.', $directory));
        }

        if (false === file_put_contents($path, self::bytes())) {
            throw new RuntimeException(\sprintf('The font cannot be written in "%s".', $path));
        }

        return $path;
    }

    /**
     * The advance of a text in units of the font, a thousandth of its size.
     */
    public static function width(string $text): int
    {
        $width = 0;

        foreach (mb_str_split($text) as $character) {
            $width += self::WIDTHS[$character] ?? self::DEFAULT_WIDTH;
        }

        return $width;
    }

    public static function bytes(): string
    {
        /** @var list<string> $characters */
        $characters = [...array_map(chr(...), range(0x20, 0x7E)), 'é', '«', '»', '…'];
        $codes = array_map(mb_ord(...), $characters);
        $advances = [self::DEFAULT_WIDTH, ...array_map(static fn (string $character): int => self::WIDTHS[$character] ?? self::DEFAULT_WIDTH, $characters)];
        $glyphs = [];
        $offsets = [0];

        foreach ($advances as $index => $advance) {
            // The space, glyph 1, has no outline.
            $glyphs[] = 1 === $index ? '' : self::box($advance);
            $offsets[] = $offsets[\count($offsets) - 1] + \strlen($glyphs[\count($glyphs) - 1]);
        }

        $count = \count($advances);
        $tables = [
            'OS/2' => self::os2(),
            'cmap' => self::cmap($codes),
            'glyf' => implode('', $glyphs),
            'head' => pack('NNNNnnJJnnnnnnnnn', 0x0001_0000, 0x0001_0000, 0, 0x5F0F_3CF5, 0x000B, self::UNITS, 0, 0, 0, 0, max($advances), self::TOP, 0, 8, 2, 1, 0),
            'hhea' => pack('Nnnnnnnnnnnnnnnnn', 0x0001_0000, 800, 0x10000 - 200, 0, max($advances), self::MARGIN, self::MARGIN, max($advances) - self::MARGIN, 1, 0, 0, 0, 0, 0, 0, 0, $count),
            'hmtx' => implode('', array_map(static fn (int $advance): string => pack('nn', $advance, self::MARGIN), $advances)),
            'loca' => pack('N*', ...$offsets),
            'maxp' => pack('Nnnnnnnnnnnnnnn', 0x0001_0000, $count, 4, 1, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0),
            'name' => self::name(),
            'post' => pack('NNnnNNNNN', 0x0003_0000, 0, 0x10000 - 100, 50, 0, 0, 0, 0, 0),
        ];

        return self::sfnt($tables);
    }

    private static function box(int $advance): string
    {
        $right = $advance - self::MARGIN;

        // One contour of four points on the curve, clockwise from the bottom left; coordinates as deltas.
        return pack('nnnnnnn', 1, self::MARGIN, 0, $right, self::TOP, 3, 0)
            .str_repeat("\x01", 4)
            .pack('n4', self::MARGIN, 0, $right - self::MARGIN, 0)
            .pack('n4', 0, self::TOP, 0, 0x10000 - self::TOP);
    }

    /**
     * @param list<int> $codes
     */
    private static function cmap(array $codes): string
    {
        $segments = [...array_map(static fn (int $code, int $index): array => [$code, $index + 1], $codes, array_keys($codes)), [0xFFFF, 0]];
        usort($segments, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $count = \count($segments);
        $search = self::powerOfTwo($count);
        $ends = '';
        $starts = '';
        $deltas = '';

        foreach ($segments as [$code, $glyph]) {
            $ends .= pack('n', $code);
            $starts .= pack('n', $code);
            $deltas .= pack('n', 0xFFFF === $code ? 1 : ($glyph - $code + 0x10000) % 0x10000);
        }

        $body = pack('nnnn', $count * 2, $search * 2, self::log2($search), ($count - $search) * 2)
            .$ends.pack('n', 0).$starts.$deltas.str_repeat(pack('n', 0), $count);
        $subtable = pack('nnn', 4, 6 + \strlen($body), 0).$body;

        return pack('nnnnN', 0, 1, 3, 1, 12).$subtable;
    }

    private static function name(): string
    {
        $records = [1 => self::FAMILY, 2 => 'Regular', 4 => self::FAMILY.' Regular', 6 => self::FAMILY.'-Regular'];
        $strings = '';
        $entries = '';

        foreach ($records as $id => $value) {
            $encoded = mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
            $entries .= pack('nnnnnn', 3, 1, 0x409, $id, \strlen($encoded), \strlen($strings));
            $strings .= $encoded;
        }

        return pack('nnn', 0, \count($records), 6 + 12 * \count($records)).$entries.$strings;
    }

    private static function os2(): string
    {
        return pack('nnnnn', 4, self::DEFAULT_WIDTH, 400, 5, 0)
            .str_repeat(pack('n', 0), 10)
            .pack('n', 0)
            .str_repeat("\0", 10)
            .pack('NNNN', 1, 0, 0, 0)
            .'PHIL'
            .pack('nnn', 0x40, 0x20, 0x2026)
            .pack('nnnnn', 800, 0x10000 - 200, 0, 800, 200)
            .pack('NN', 1, 0)
            .pack('nnnnn', 500, self::TOP, 0, 0x20, 1);
    }

    /**
     * @param array<string, string> $tables in the order of their tags
     */
    private static function sfnt(array $tables): string
    {
        $count = \count($tables);
        $search = self::powerOfTwo($count);
        $directory = pack('Nnnnn', 0x0001_0000, $count, $search * 16, self::log2($search), ($count - $search) * 16);
        $offset = 12 + 16 * $count;
        $data = '';

        foreach ($tables as $tag => $table) {
            $padded = str_pad($table, (\strlen($table) + 3) & ~3, "\0");
            $directory .= $tag.pack('NNN', self::checksum($padded), $offset, \strlen($table));
            $data .= $padded;
            $offset += \strlen($padded);
        }

        return $directory.$data;
    }

    /**
     * The largest power of two that is not above a count, for the search fields of the tables.
     */
    private static function powerOfTwo(int $count): int
    {
        $power = 1;

        while ($power * 2 <= $count) {
            $power *= 2;
        }

        return $power;
    }

    private static function log2(int $power): int
    {
        return \strlen(decbin($power)) - 1;
    }

    private static function checksum(string $padded): int
    {
        $sum = 0;

        $words = unpack('N*', $padded);

        foreach (false === $words ? [] : $words as $word) {
            $sum = ($sum + (\is_int($word) ? $word : 0)) & 0xFFFF_FFFF;
        }

        return $sum;
    }
}
