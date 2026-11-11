<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit\Fixture;

/**
 * Writes the TrueType font of the tests, 1000 units to the em, each glyph one rectangle:
 *
 * - capitals and digits from 50 to 550 across and from the baseline to 700 up, on an advance of 600; "W" from 50 to
 *   950 on an advance of 1000;
 * - small letters from 50 to 450 across and from the baseline to 500 up, on an advance of 500; "g", "j", "p", "q" and
 *   "y" from 200 below the baseline; "é" up to 700;
 * - ".", ",", "!" and "?" from 50 to 150 across and up to 100; "-" from 250 to 300 up; "'" from 600 to 700 up, on an
 *   advance of 200;
 * - the space and the no-break space draw nothing, on an advance of 250.
 */
final class FontWriter
{
    public static function bytes(): string
    {
        $characters = [32 => [250, null]];

        foreach (range(\ord('A'), \ord('Z')) as $code) {
            $characters[$code] = 'W' === \chr($code) ? [1000, [50, 0, 950, 700]] : [600, [50, 0, 550, 700]];
        }

        foreach (range(\ord('a'), \ord('z')) as $code) {
            $characters[$code] = [500, [50, str_contains('gjpqy', \chr($code)) ? -200 : 0, 450, 500]];
        }

        foreach (range(\ord('0'), \ord('9')) as $code) {
            $characters[$code] = [600, [50, 0, 550, 700]];
        }

        foreach (['.', ',', '!', '?'] as $character) {
            $characters[\ord($character[0])] = [200, [50, 0, 150, 100]];
        }

        $characters[\ord('-')] = [200, [50, 250, 150, 300]];
        $characters[\ord("'")] = [200, [50, 600, 150, 700]];
        $characters[0xA0] = [250, null];
        $characters[0xE9] = [500, [50, 0, 450, 700]];
        ksort($characters);

        $glyphs = [[500, null], ...array_values($characters)];
        $glyf = '';
        $loca = '';
        $hmtx = '';

        foreach ($glyphs as [$advance, $box]) {
            $loca .= pack('N', \strlen($glyf));
            $hmtx .= pack('nn', $advance, null === $box ? 0 : $box[0]);
            $glyf .= null === $box ? '' : self::rectangle(...$box);
        }

        $loca .= pack('N', \strlen($glyf));

        return self::assemble([
            'OS/2' => pack('nnnnn', 4, 500, 400, 5, 0).str_repeat("\0", 76).pack('nn', 32, 0xE9).str_repeat("\0", 6),
            'cmap' => self::cmap(array_keys($characters)),
            'glyf' => $glyf,
            'head' => pack('NNNNnn', 0x00010000, 0x00010000, 0, 0x5F0F3CF5, 0x000B, 1000)
                .str_repeat("\0", 16)
                .pack('nnnn', 0, 0x10000 - 200, 1000, 700)
                .pack('nnnnn', 0, 8, 2, 1, 0),
            'hhea' => pack('N', 0x00010000).pack('nnnn', 800, 0x10000 - 200, 0, 1000).pack('nnnnnn', 0, 0, 950, 1, 0, 0)
                .str_repeat("\0", 8).pack('nn', 0, \count($glyphs)),
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => pack('Nn', 0x00005000, \count($glyphs)),
            'name' => self::name('Block Test'),
            'post' => pack('N', 0x00030000).str_repeat("\0", 28),
        ]);
    }

    private static function rectangle(int $left, int $bottom, int $right, int $top): string
    {
        // One contour of four points on the curve, clockwise from the bottom left, each coordinate a 16-bit delta.
        $points = [[$left, $bottom], [$left, $top], [$right, $top], [$right, $bottom]];
        $xs = '';
        $ys = '';
        $previous = [0, 0];

        foreach ($points as $point) {
            $xs .= pack('n', ($point[0] - $previous[0]) & 0xFFFF);
            $ys .= pack('n', ($point[1] - $previous[1]) & 0xFFFF);
            $previous = $point;
        }

        return pack('nnnnn', 1, $left & 0xFFFF, $bottom & 0xFFFF, $right, $top).pack('nn', 3, 0).str_repeat("\x01", 4).$xs.$ys;
    }

    /**
     * A format 4 character map, one segment per character, mapped to glyphs 1, 2, 3… in code point order.
     *
     * @param list<int> $codes
     */
    private static function cmap(array $codes): string
    {
        $codes[] = 0xFFFF;
        $count = \count($codes);
        $ends = '';
        $starts = '';
        $deltas = '';

        foreach ($codes as $index => $code) {
            $ends .= pack('n', $code);
            $starts .= pack('n', $code);
            $deltas .= pack('n', 0xFFFF === $code ? 1 : ($index + 1 - $code) & 0xFFFF);
        }

        $search = 2 ** (int) floor(log($count, 2));
        $body = pack('nnnn', $count * 2, $search * 2, (int) log($search, 2), 2 * $count - 2 * $search)
            .$ends.pack('n', 0).$starts.$deltas.str_repeat("\0\0", $count);

        return pack('nn', 0, 1).pack('nnN', 3, 1, 12).pack('nnn', 4, 6 + \strlen($body), 0).$body;
    }

    private static function name(string $family): string
    {
        $text = mb_convert_encoding($family, 'UTF-16BE', 'UTF-8');
        $regular = mb_convert_encoding('Regular', 'UTF-16BE', 'UTF-8');

        return pack('nnn', 0, 2, 6 + 2 * 12)
            .pack('nnnnnn', 3, 1, 0x409, 1, \strlen($text), 0)
            .pack('nnnnnn', 3, 1, 0x409, 2, \strlen($regular), \strlen($text))
            .$text.$regular;
    }

    /**
     * @param array<string, string> $tables sorted by tag
     */
    private static function assemble(array $tables): string
    {
        $count = \count($tables);
        $search = 2 ** (int) floor(log($count, 2));
        $offset = 12 + 16 * $count;
        $directory = '';
        $body = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.pack('NNN', 0, $offset + \strlen($body), \strlen($data));
            $body .= $data.str_repeat("\0", (4 - \strlen($data) % 4) % 4);
        }

        return pack('Nnnnn', 0x00010000, $count, $search * 16, (int) log($search, 2), $count * 16 - $search * 16).$directory.$body;
    }
}
