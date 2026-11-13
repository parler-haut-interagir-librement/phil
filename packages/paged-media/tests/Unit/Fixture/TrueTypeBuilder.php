<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

/**
 * Writes small TrueType fonts for the tests: a missing glyph, one square glyph per character given, and composite
 * glyphs made of others, with the embedding bits, the character map formats and the tables each test needs.
 */
final class TrueTypeBuilder
{
    /** @var array<int, int> code point to advance width, in font units */
    private array $characters = [];

    /** @var array<int, list<int>> composite code point to the code points of its components */
    private array $composites = [];

    /** @var array<int, string> code point to the bytes of its glyph, written as they are */
    private array $rawGlyphs = [];

    private bool $missingGlyphOutline = false;

    private int $embedding = 0;

    private bool $format12 = false;

    private bool $longLoca = false;

    private ?string $postScriptName = 'TestSans-Regular';

    private int $unitsPerEm = 1000;

    private int $os2Version = 4;

    private bool $withOs2 = true;

    private bool $withPost = true;

    private float $italicAngle = 0.0;

    private int $fixedPitch = 0;

    private ?int $hMetrics = null;

    private ?int $os2Length = null;

    /** @var array<string, string> tables written as they are, in place of the ones built or besides them */
    private array $rawTables = [];

    private ?string $lastTable = null;

    private int $cut = 0;

    private string $version = "\0\1\0\0";

    /** @var list<string> the tables to leave out */
    private array $without = [];

    /**
     * @param array<int, int> $characters code point to advance width
     */
    public static function with(array $characters): self
    {
        $builder = new self();
        $builder->characters = $characters;

        return $builder;
    }

    /**
     * @param list<int> $components the code points of its components, each also in `with()`
     */
    public function composite(int $codePoint, int $advance, array $components): self
    {
        $this->characters[$codePoint] = $advance;
        $this->composites[$codePoint] = $components;

        return $this;
    }

    /**
     * A glyph written as the bytes given, for the code point given.
     */
    public function glyph(int $codePoint, int $advance, string $data): self
    {
        $this->characters[$codePoint] = $advance;
        $this->rawGlyphs[$codePoint] = $data;

        return $this;
    }

    /**
     * Gives the missing glyph, 0, an outline instead of none.
     */
    public function missingGlyphOutline(): self
    {
        $this->missingGlyphOutline = true;

        return $this;
    }

    /**
     * The number of advances in `hmtx`, which `hhea` declares; the glyphs past it share the last one.
     */
    public function hMetrics(int $count): self
    {
        $this->hMetrics = $count;

        return $this;
    }

    /**
     * Cuts the `OS/2` table, or pads it with zeros, to the length given; its cap height is at 88.
     */
    public function os2Length(int $length): self
    {
        $this->os2Length = $length;

        return $this;
    }

    /**
     * A table written as the bytes given, in place of the one built or besides them.
     */
    public function table(string $tag, string $data): self
    {
        $this->rawTables[$tag] = $data;

        return $this;
    }

    /**
     * Writes the table given at the end of the file, without padding, short of the bytes cut from its end and from
     * its declared length.
     */
    public function last(string $tag, int $cut = 0): self
    {
        $this->lastTable = $tag;
        $this->cut = $cut;

        return $this;
    }

    public function embedding(int $bits): self
    {
        $this->embedding = $bits;

        return $this;
    }

    public function format12(): self
    {
        $this->format12 = true;

        return $this;
    }

    public function longLoca(): self
    {
        $this->longLoca = true;

        return $this;
    }

    public function named(?string $postScriptName): self
    {
        $this->postScriptName = $postScriptName;

        return $this;
    }

    public function unitsPerEm(int $units): self
    {
        $this->unitsPerEm = $units;

        return $this;
    }

    public function os2Version(int $version): self
    {
        $this->os2Version = $version;

        return $this;
    }

    public function withoutOs2(): self
    {
        $this->withOs2 = false;

        return $this;
    }

    public function withoutPost(): self
    {
        $this->withPost = false;

        return $this;
    }

    public function italic(float $angle): self
    {
        $this->italicAngle = $angle;

        return $this;
    }

    /**
     * @param int $value the `isFixedPitch` field of `post`, any value but 0 for a fixed pitch
     */
    public function fixedPitch(int $value = 1): self
    {
        $this->fixedPitch = $value;

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function without(string $table): self
    {
        $this->without[] = $table;

        return $this;
    }

    public function build(): string
    {
        ksort($this->characters);
        $codePoints = array_keys($this->characters);
        $glyphOf = array_flip($codePoints);
        $glyphs = [$this->missingGlyphOutline ? $this->squareGlyph(5) : ''];
        $advances = [500];

        foreach ($codePoints as $index => $codePoint) {
            $glyphs[] = $this->rawGlyphs[$codePoint] ?? (isset($this->composites[$codePoint])
                ? $this->compositeGlyph(array_map(static fn (int $component): int => $glyphOf[$component] + 1, $this->composites[$codePoint]))
                : $this->squareGlyph(10 * ($index + 1)));
            $advances[] = $this->characters[$codePoint];
        }

        $glyf = '';
        $offsets = [];

        foreach ($glyphs as $data) {
            $offsets[] = \strlen($glyf);
            $glyf .= $data;
        }

        $offsets[] = \strlen($glyf);
        $tables = [
            'head' => $this->head(),
            'hhea' => pack('N', 0x00010000).pack('n*', 800, 0x10000 - 200, 0, 1200, 0, 0, 1000, 1, 0, 0, 0, 0, 0, 0, 0).pack('n', $this->hMetrics ?? \count($advances)),
            'maxp' => pack('Nn', 0x00005000, \count($glyphs)),
            'hmtx' => implode('', array_map(static fn (int $advance): string => pack('nn', $advance, 0), $advances)),
            'cmap' => $this->cmap($codePoints),
            'loca' => implode('', array_map(fn (int $offset): string => $this->longLoca ? pack('N', $offset) : pack('n', intdiv($offset, 2)), $offsets)),
            'glyf' => $glyf,
            'cvt ' => pack('nn', 0, 0),
        ];

        if (null !== $this->postScriptName) {
            $tables['name'] = $this->name($this->postScriptName);
        }

        if ($this->withOs2) {
            $tables['OS/2'] = $this->os2();
        }

        if ($this->withPost) {
            $whole = (int) floor($this->italicAngle);
            $tables['post'] = pack('Nnn', 0x00030000, $whole & 0xFFFF, (int) round(($this->italicAngle - $whole) * 65536)).pack('nnN', 0, 0, $this->fixedPitch).str_repeat("\0", 16);
        }

        $tables = [...$tables, ...$this->rawTables];

        foreach ($this->without as $table) {
            unset($tables[$table]);
        }

        return $this->assemble($tables);
    }

    private function head(): string
    {
        return pack('NNNNnn', 0x00010000, 0x00018000, 0x1234ABCD, 0x5F0F3CF5, 0x000B, $this->unitsPerEm)
            .str_repeat("\0", 16)
            .pack('nnnn', 0x10000 - 100, 0x10000 - 200, 1100, 900)
            .pack('nnnnn', 0, 8, 2, $this->longLoca ? 1 : 0, 0);
    }

    private function os2(): string
    {
        $table = pack('nnnnn', $this->os2Version, 500, 400, 5, $this->embedding).str_repeat("\0", 76).pack('nn', 500, 700).str_repeat("\0", 6);

        return substr(str_pad($table, $this->os2Length ?? 0, "\0"), 0, $this->os2Length ?? ($this->os2Version >= 2 ? 96 : 78));
    }

    /**
     * @param list<int> $codePoints
     */
    private function cmap(array $codePoints): string
    {
        if ($this->format12) {
            $groups = [];

            foreach ($codePoints as $index => $codePoint) {
                $groups[] = [$codePoint, $codePoint, $index + 1];
            }

            return self::cmapTable([[3, 10, self::format12Subtable($groups)]]);
        }

        // One segment per character, the first through idDelta and the others through idRangeOffset.
        $segments = [];

        foreach ($codePoints as $index => $codePoint) {
            $segments[] = 0 === $index ? [$codePoint, $codePoint, $index + 1 - $codePoint, null] : [$codePoint, $codePoint, 0, [$index + 1]];
        }

        return self::cmapTable([[3, 1, self::format4Subtable($segments)]]);
    }

    /**
     * A `cmap` table of the encoding records given, each with its own copy of its subtable.
     *
     * @param list<array{int, int, string}> $records platform, encoding and subtable
     * @param array{int, int, string}|null  $hidden  a record written after the others but left out of their count
     */
    public static function cmapTable(array $records, int $version = 0, ?array $hidden = null): string
    {
        $written = null === $hidden ? $records : [...$records, $hidden];
        $offset = 4 + 8 * \count($written);
        $directory = pack('nn', $version, \count($records));
        $subtables = '';

        foreach ($written as [$platform, $encoding, $subtable]) {
            $directory .= pack('nnN', $platform, $encoding, $offset + \strlen($subtables));
            $subtables .= $subtable;
        }

        return $directory.$subtables;
    }

    /**
     * A format 4 subtable, followed by the final segment of 0xFFFF. Each segment maps its code points either by
     * adding its delta, or through the glyphs given, one per code point, with its delta added to each but 0.
     *
     * @param list<array{int, int, int, list<int>|null}> $segments start, end, delta and glyphs
     */
    public static function format4Subtable(array $segments, int $finalDelta = 1): string
    {
        $segments[] = [0xFFFF, 0xFFFF, $finalDelta, null];
        $count = \count($segments);
        $selector = (int) floor(log($count, 2));
        $ends = '';
        $starts = '';
        $deltas = '';
        $ranges = '';
        $glyphIds = '';

        foreach ($segments as $position => [$start, $end, $delta, $glyphs]) {
            $ends .= pack('n', $end);
            $starts .= pack('n', $start);
            $deltas .= pack('n', $delta & 0xFFFF);
            $ranges .= pack('n', null === $glyphs ? 0 : 2 * ($count - $position) + \strlen($glyphIds));
            $glyphIds .= pack('n*', ...$glyphs ?? []);
        }

        $body = pack('nnnn', 2 * $count, 2 << $selector, $selector, 2 * $count - (2 << $selector)).$ends.pack('n', 0).$starts.$deltas.$ranges.$glyphIds;

        return pack('nnn', 4, 6 + \strlen($body), 0).$body;
    }

    /**
     * A format 12 subtable of the groups given.
     *
     * @param list<array{int, int, int}> $groups first code point, last code point and first glyph
     */
    public static function format12Subtable(array $groups, int $language = 0): string
    {
        $data = implode('', array_map(static fn (array $group): string => pack('NNN', ...$group), $groups));

        return pack('nnNNN', 12, 0, 16 + \strlen($data), $language, \count($groups)).$data;
    }

    private function squareGlyph(int $size): string
    {
        // One contour of four on-curve points.
        return pack('nnnnn', 1, 0, 0, $size, $size)
            .pack('nn', 3, 0)
            .str_repeat(\chr(0x01), 4)
            .pack('nnnn', 0, $size, 0, 0x10000 - $size)
            .pack('nnnn', 0, 0, $size, 0);
    }

    /**
     * @param list<int> $components the glyphs, each placed at the origin; the second with a scale, the third with two
     */
    private function compositeGlyph(array $components): string
    {
        $data = pack('nnnnn', 0xFFFF, 0, 0, 100, 100);
        $last = array_key_last($components);

        foreach ($components as $index => $glyph) {
            $flags = ($index === $last ? 0 : 0x0020) | match ($index % 4) {
                1 => 0x0008,
                2 => 0x0040,
                3 => 0x0080 | 0x0001,
                default => 0,
            };
            $data .= pack('nn', $flags, $glyph).(0 !== ($flags & 0x0001) ? pack('nn', 0, 0) : pack('CC', 0, 0)).match ($index % 4) {
                1 => pack('n', 0x4000),
                2 => pack('nn', 0x4000, 0x4000),
                3 => pack('nnnn', 0x4000, 0, 0, 0x4000),
                default => '',
            };
        }

        return $data.str_repeat("\0", \strlen($data) % 2);
    }

    private function name(string $postScriptName): string
    {
        $text = mb_convert_encoding($postScriptName, 'UTF-16BE', 'UTF-8');

        // The PostScript name comes first in the storage, so that reading past its length reads the family name.
        return pack('nnn', 0, 2, 6 + 2 * 12)
            .pack('nnnnnn', 3, 1, 0x409, 1, 8, \strlen($text))
            .pack('nnnnnn', 3, 1, 0x409, 6, \strlen($text), 0)
            .$text.mb_convert_encoding('Test', 'UTF-16BE', 'UTF-8');
    }

    /**
     * @param array<string, string> $tables
     */
    private function assemble(array $tables): string
    {
        ksort($tables, \SORT_STRING);
        $offset = 12 + 16 * \count($tables);
        $order = array_keys($tables);

        if (null !== $this->lastTable) {
            $order = [...array_diff($order, [$this->lastTable]), $this->lastTable];
            $tables[$this->lastTable] = substr($tables[$this->lastTable], 0, \strlen($tables[$this->lastTable]) - $this->cut);
        }

        $offsets = [];
        $body = '';

        foreach ($order as $tag) {
            $offsets[$tag] = $offset + \strlen($body);
            $body .= $tag === $this->lastTable ? $tables[$tag] : $tables[$tag].str_repeat("\0", (4 - \strlen($tables[$tag]) % 4) % 4);
        }

        $directory = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.pack('NNN', 0, $offsets[$tag], \strlen($data));
        }

        return $this->version.pack('nnnn', \count($tables), 0, 0, 0).$directory.$body;
    }
}
