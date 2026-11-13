<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Font;

use Phil\PagedMedia\Exception\InvalidFont;
use Phil\PagedMedia\Pdf\Bytes;

/**
 * A TrueType font read from its file, to measure text and to be embedded in a document — reduced to the glyphs the
 * document prints when its licence allows it, whole when it forbids subsetting, refused when it forbids embedding.
 *
 * It reads TrueType outlines (`glyf`) only: an OpenType font with PostScript outlines (`CFF `) is refused.
 */
final class TrueTypeFont implements Font
{
    /** The `OS/2` embedding bits that forbid printing the font in a document: restricted licence, bitmap only. */
    private const int FORBIDS_EMBEDDING = 0x0002 | 0x0200;

    /** The `OS/2` bit that forbids embedding anything but the whole font. */
    private const int FORBIDS_SUBSETTING = 0x0100;

    /** The tables a subset keeps when the font has them, besides `glyf`, `loca` and `head`. */
    private const array KEPT_TABLES = ['cvt ', 'fpgm', 'hhea', 'hmtx', 'maxp', 'prep'];

    /** @var array<string, array{int, int}> each table's offset and length */
    private array $tables;

    private int $unitsPerEm;

    private bool $longLoca;

    private readonly int $glyphCount;

    /** @var list<int> each glyph's advance width, in font units */
    private array $advances;

    /** The advance of the glyphs past the metrics, which share the last one. */
    private int $lastAdvance;

    /** @var array<int, int> code point to glyph */
    private array $glyphsByCodePoint;

    private string $postScriptName;

    private int $embedding;

    /** @var array{int, int, int, int} */
    private array $boundingBox;

    private int $ascent;

    private int $descent;

    private int $capHeight;

    private float $italicAngle;

    private bool $fixedPitch;

    private function __construct(
        private readonly string $bytes,
    ) {
        $this->readTableDirectory();
        $this->readHead();
        $this->glyphCount = $this->uint16($this->table('maxp') + 4);
        $this->readMetrics();
        $this->readCharacterMap();
        $this->readNames();
        $this->readOs2AndPost();

        if (0 !== ($this->embedding & self::FORBIDS_EMBEDDING)) {
            throw InvalidFont::because(\sprintf('The licence of the font "%s" forbids embedding it in a document.', $this->postScriptName));
        }
    }

    public static function fromBytes(string $bytes): self
    {
        return new self($bytes);
    }

    public static function fromFile(string $path): self
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $bytes) {
            throw InvalidFont::because(\sprintf('The font file "%s" cannot be read.', $path));
        }

        return new self($bytes);
    }

    public function postScriptName(): string
    {
        return $this->postScriptName;
    }

    /**
     * Whether the licence lets a document embed only the glyphs it prints.
     */
    public function allowsSubsetting(): bool
    {
        return 0 === ($this->embedding & self::FORBIDS_SUBSETTING);
    }

    public function width(string $text, float $size): float
    {
        $width = 0;

        foreach ($this->glyphs($text) as $glyph) {
            $width += $this->advance($glyph);
        }

        return $width * $size / 1000;
    }

    /**
     * The glyphs that print a text, one per character; a character the font lacks prints as its missing glyph, 0.
     *
     * @return list<int>
     */
    public function glyphs(string $text): array
    {
        return array_map(fn (string $character): int => $this->glyphsByCodePoint[mb_ord($character)] ?? 0, mb_str_split($text));
    }

    /**
     * Every character the font prints, by code point in the order of its character map, with its glyph.
     *
     * @return array<int, int>
     */
    public function characters(): array
    {
        return array_filter($this->glyphsByCodePoint, static fn (int $glyph): bool => 0 !== $glyph);
    }

    /**
     * A glyph's advance width, in thousandths of the size.
     */
    public function advance(int $glyph): int
    {
        return (int) round(($this->advances[$glyph] ?? $this->lastAdvance) * 1000 / $this->unitsPerEm);
    }

    /**
     * The values of the font descriptor, in thousandths of the size.
     *
     * @return array{boundingBox: array{int, int, int, int}, ascent: int, descent: int, capHeight: int, italicAngle: float, fixedPitch: bool}
     */
    public function descriptor(): array
    {
        $scale = fn (int $value): int => (int) round($value * 1000 / $this->unitsPerEm);

        return [
            'boundingBox' => [$scale($this->boundingBox[0]), $scale($this->boundingBox[1]), $scale($this->boundingBox[2]), $scale($this->boundingBox[3])],
            'ascent' => $scale($this->ascent),
            'descent' => $scale($this->descent),
            'capHeight' => $scale($this->capHeight),
            'italicAngle' => $this->italicAngle,
            'fixedPitch' => $this->fixedPitch,
        ];
    }

    /**
     * The font file to embed: the glyphs given, the missing glyph and the components of composite glyphs, under their
     * own numbers so that a glyph keeps its identifier; or the whole file when the licence forbids subsetting.
     *
     * @param list<int> $glyphs
     */
    public function subset(array $glyphs): string
    {
        if (!$this->allowsSubsetting()) {
            return $this->bytes;
        }

        $kept = $this->withComponents([0, ...$glyphs]);
        $glyf = '';
        $offsets = [];

        for ($glyph = 0; $glyph < $this->glyphCount; ++$glyph) {
            $offsets[] = \strlen($glyf);

            if (isset($kept[$glyph])) {
                $glyf .= $this->padded($this->glyphData($glyph));
            }
        }

        $offsets[] = \strlen($glyf);
        $tables = ['glyf' => $glyf, 'loca' => implode('', array_map(Bytes::uint32(...), $offsets))];
        // The long `loca` format, and a checksum adjustment the reader recomputes.
        $head = $this->tableBytes('head');
        $tables['head'] = substr($head, 0, 8)."\0\0\0\0".substr($head, 12, 38)."\0\1".substr($head, 52);

        foreach (self::KEPT_TABLES as $tag) {
            if (isset($this->tables[$tag])) {
                $tables[$tag] = $this->tableBytes($tag);
            }
        }

        return $this->assemble($tables);
    }

    /**
     * @param array<string, string> $tables
     */
    private function assemble(array $tables): string
    {
        ksort($tables, \SORT_STRING);
        $count = \count($tables);
        $power = 1;
        $selector = 0;

        while ($power * 2 <= $count) {
            $power *= 2;
            ++$selector;
        }

        $header = Bytes::uint32(0x00010000).Bytes::uint16($count).Bytes::uint16($power * 16).Bytes::uint16($selector).Bytes::uint16(($count - $power) * 16);
        $offset = 12 + 16 * $count;
        $directory = '';
        $body = '';

        foreach ($tables as $tag => $data) {
            $directory .= $tag.Bytes::uint32($this->checksum($data)).Bytes::uint32($offset + \strlen($body)).Bytes::uint32(\strlen($data));
            $body .= $this->padded($data);
        }

        return $header.$directory.$body;
    }

    private function checksum(string $data): int
    {
        $sum = 0;

        foreach (str_split($this->padded($data), 4) as $word) {
            $sum = ($sum + Bytes::readUint32($word, 0)) & 0xFFFFFFFF;
        }

        return $sum;
    }

    /**
     * The data followed by the zeros that bring its length to a multiple of four.
     */
    private function padded(string $data): string
    {
        return $data.str_repeat("\0", (4 - \strlen($data) % 4) % 4);
    }

    /**
     * @param list<int> $glyphs
     *
     * @return array<int, int> each glyph kept, by itself
     */
    private function withComponents(array $glyphs): array
    {
        $kept = [];
        $pending = $glyphs;

        while ([] !== $pending) {
            $glyph = array_pop($pending);

            if (isset($kept[$glyph]) || $glyph < 0 || $glyph >= $this->glyphCount) {
                continue;
            }

            $kept[$glyph] = $glyph;
            $pending = [...$pending, ...$this->components($glyph)];
        }

        return $kept;
    }

    /**
     * The glyphs a composite glyph is made of; none for a simple or empty glyph.
     *
     * @return list<int>
     */
    private function components(int $glyph): array
    {
        $data = $this->glyphData($glyph);
        $components = [];
        // A negative number of contours, in its first two bytes, makes a composite glyph; its components follow its
        // 10-byte header, each with its flags and its glyph, as long as the flags announce another one.
        $more = '' !== $data && \ord($data[0]) >= 0x80;
        $at = 10;

        while ($more && $at + 4 <= \strlen($data)) {
            $flags = Bytes::readUint16($data, $at);
            $components[] = Bytes::readUint16($data, $at + 2);
            $more = 0 !== ($flags & 0x0020);
            $at += 4 + (0 !== ($flags & 0x0001) ? 4 : 2) + match (true) {
                0 !== ($flags & 0x0008) => 2,
                0 !== ($flags & 0x0040) => 4,
                0 !== ($flags & 0x0080) => 8,
                default => 0,
            };
        }

        return $components;
    }

    private function glyphData(int $glyph): string
    {
        $loca = $this->table('loca');
        [$start, $end] = $this->longLoca
            ? [$this->uint32($loca + 4 * $glyph), $this->uint32($loca + 4 * $glyph + 4)]
            : [2 * $this->uint16($loca + 2 * $glyph), 2 * $this->uint16($loca + 2 * $glyph + 2)];

        return $end > $start ? substr($this->bytes, $this->table('glyf') + $start, $end - $start) : '';
    }

    private function readTableDirectory(): void
    {
        if (\strlen($this->bytes) < 12) {
            throw InvalidFont::because('A font file is at least 12 bytes long.');
        }

        $version = substr($this->bytes, 0, 4);

        if ('OTTO' === $version) {
            throw InvalidFont::because('The font has PostScript outlines (CFF); only TrueType outlines can be embedded.');
        }

        if ("\0\1\0\0" !== $version && 'true' !== $version) {
            throw InvalidFont::because('The file is not a TrueType font.');
        }

        $this->tables = [];
        $count = $this->uint16(4);

        for ($index = 0; $index < $count; ++$index) {
            $entry = 12 + 16 * $index;
            $offset = $this->uint32($entry + 8);
            $length = $this->uint32($entry + 12);

            if ($offset + $length > \strlen($this->bytes)) {
                throw InvalidFont::because('A table of the font runs past the end of the file.');
            }

            $this->tables[substr($this->bytes, $entry, 4)] = [$offset, $length];
        }

        foreach (['head', 'hhea', 'hmtx', 'maxp', 'cmap', 'loca', 'glyf'] as $required) {
            if (!isset($this->tables[$required])) {
                throw InvalidFont::because(\sprintf('The font has no "%s" table.', $required));
            }
        }
    }

    private function readHead(): void
    {
        $head = $this->table('head');
        $this->unitsPerEm = $this->uint16($head + 18);

        if (0 === $this->unitsPerEm) {
            throw InvalidFont::because('The font declares no units per em.');
        }

        $this->boundingBox = [$this->int16($head + 36), $this->int16($head + 38), $this->int16($head + 40), $this->int16($head + 42)];
        $this->longLoca = 1 === $this->int16($head + 50);
    }

    private function readMetrics(): void
    {
        $hhea = $this->table('hhea');
        $this->ascent = $this->int16($hhea + 4);
        $this->descent = $this->int16($hhea + 6);
        $count = max(1, $this->uint16($hhea + 34));
        $hmtx = $this->table('hmtx');
        $this->advances = [];

        for ($glyph = 0; $glyph < $count; ++$glyph) {
            $this->advances[] = $this->uint16($hmtx + 4 * $glyph);
        }

        $this->lastAdvance = $this->advances[$count - 1];
    }

    /**
     * Reads the Unicode map: format 12 for every plane, else format 4 for the basic plane.
     */
    private function readCharacterMap(): void
    {
        $cmap = $this->table('cmap');
        $subtables = [];

        for ($index = 0, $count = $this->uint16($cmap + 2); $index < $count; ++$index) {
            $entry = $cmap + 4 + 8 * $index;
            $offset = $cmap + $this->uint32($entry + 4);
            $subtables[$this->uint16($offset)][$this->uint16($entry).'.'.$this->uint16($entry + 2)] = $offset;
        }

        $this->glyphsByCodePoint = [];
        $format12 = $subtables[12]['3.10'] ?? $subtables[12]['0.4'] ?? null;

        if (null !== $format12) {
            for ($group = 0, $groups = $this->uint32($format12 + 12); $group < $groups; ++$group) {
                $at = $format12 + 16 + 12 * $group;
                $first = $this->uint32($at);

                for ($codePoint = $first, $last = min($this->uint32($at + 4), 0x10FFFF); $codePoint <= $last; ++$codePoint) {
                    $this->glyphsByCodePoint[$codePoint] = $this->uint32($at + 8) + $codePoint - $first;
                }
            }

            return;
        }

        $format4 = $subtables[4]['3.1'] ?? $subtables[4]['0.3'] ?? throw InvalidFont::because('The font has no Unicode character map.');
        $segments = intdiv($this->uint16($format4 + 6), 2);
        $ends = $format4 + 14;
        $starts = $ends + 2 * $segments + 2;
        $deltas = $starts + 2 * $segments;
        $ranges = $deltas + 2 * $segments;

        for ($segment = 0; $segment < $segments; ++$segment) {
            $start = $this->uint16($starts + 2 * $segment);
            $end = $this->uint16($ends + 2 * $segment);
            $delta = $this->uint16($deltas + 2 * $segment);
            $rangeAt = $ranges + 2 * $segment;
            $range = $this->uint16($rangeAt);

            for ($codePoint = $start; $codePoint <= $end; ++$codePoint) {
                $glyph = 0 === $range ? $codePoint + $delta : $this->uint16($rangeAt + $range + 2 * ($codePoint - $start));

                if (0 !== $range && 0 !== $glyph) {
                    $glyph += $delta;
                }

                $this->glyphsByCodePoint[$codePoint] = $glyph & 0xFFFF;
            }
        }
    }

    private function readNames(): void
    {
        $this->postScriptName = 'Embedded';

        if (!isset($this->tables['name'])) {
            return;
        }

        $name = $this->table('name');
        $storage = $name + $this->uint16($name + 4);

        for ($index = 0, $count = $this->uint16($name + 2); $index < $count; ++$index) {
            $record = $name + 6 + 12 * $index;

            if (6 !== $this->uint16($record + 6)) {
                continue;
            }

            $text = substr($this->bytes, $storage + $this->uint16($record + 10), $this->uint16($record + 8));
            $text = 1 === $this->uint16($record) ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-16BE');
            $clean = preg_replace('/[^A-Za-z0-9-]/', '', $text);

            if (null !== $clean && '' !== $clean) {
                $this->postScriptName = $clean;

                return;
            }
        }
    }

    private function readOs2AndPost(): void
    {
        $this->embedding = 0;
        $this->capHeight = $this->ascent;

        if (isset($this->tables['OS/2'])) {
            $os2 = $this->table('OS/2');
            $this->embedding = $this->uint16($os2 + 8);

            if ($this->uint16($os2) >= 2 && $this->tables['OS/2'][1] >= 90) {
                $this->capHeight = $this->int16($os2 + 88);
            }
        }

        $this->italicAngle = 0.0;
        $this->fixedPitch = false;

        if (isset($this->tables['post'])) {
            $post = $this->table('post');
            $this->italicAngle = $this->int16($post + 4) + $this->uint16($post + 6) / 65536;
            $this->fixedPitch = 0 !== $this->uint32($post + 12);
        }
    }

    private function table(string $tag): int
    {
        return $this->tables[$tag][0];
    }

    private function tableBytes(string $tag): string
    {
        return substr($this->bytes, $this->tables[$tag][0], $this->tables[$tag][1]);
    }

    private function uint16(int $at): int
    {
        if ($at + 2 > \strlen($this->bytes)) {
            throw InvalidFont::because('The font file is truncated.');
        }

        return Bytes::readUint16($this->bytes, $at);
    }

    private function int16(int $at): int
    {
        $value = $this->uint16($at);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function uint32(int $at): int
    {
        if ($at + 4 > \strlen($this->bytes)) {
            throw InvalidFont::because('The font file is truncated.');
        }

        return Bytes::readUint32($this->bytes, $at);
    }
}
