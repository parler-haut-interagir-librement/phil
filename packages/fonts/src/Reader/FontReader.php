<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

use Closure;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Exception\UnsupportedFont;
use Phil\Fonts\Font;
use Phil\Fonts\Format;

/**
 * Reads the container of a font file — a bare `sfnt`, a collection, WOFF or WOFF2 — down to the tables of each face.
 *
 * The work a file can cause is bounded, since fonts are uploaded: its length, the tables of a face, the faces of a
 * collection, and the bytes its tables come to once copied or decompressed, paid for before each copy or
 * decompression. Only the tables the face reader reads are copied or decompressed; the others are checked to be within
 * the file and kept empty, which still tells the outlines.
 *
 * @internal
 */
final readonly class FontReader
{
    /** The longest font file read, in bytes: 32 MiB. */
    public const int MAX_LENGTH = 33_554_432;

    /** The most tables a face lists. */
    public const int MAX_TABLES = 256;

    /** The most faces a collection holds. */
    public const int MAX_FACES = 64;

    /** The most bytes the tables and metadata of a file come to, copied or decompressed: 64 MiB. */
    public const int MAX_TABLE_BYTES = 67_108_864;

    /** The tables the face reader reads. */
    private const array READ = ['name', 'OS/2', 'head', 'post', 'fvar', 'cmap'];

    /** The tags a WOFF2 table directory names by their index, in the order of the WOFF2 specification. */
    private const array WOFF2_TAGS = [
        'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post', 'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ',
        'VORG', 'EBDT', 'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea', 'vmtx', 'BASE', 'GDEF', 'GPOS',
        'GSUB', 'EBSC', 'JSTF', 'MATH', 'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar', 'bdat', 'bloc',
        'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar', 'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop',
        'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill',
    ];

    /**
     * @param (Closure(string, int): (string|false))|null $brotli
     *
     * @return array{Format, non-empty-list<Font>, ?string} the format, the faces and the WOFF extended metadata
     */
    public static function read(string $bytes, ?Closure $brotli): array
    {
        if (\strlen($bytes) > self::MAX_LENGTH) {
            throw InvalidFont::because('A font file is at most 32 MiB long.');
        }

        $file = new Binary($bytes);
        $budget = new Budget(self::MAX_TABLE_BYTES);

        if ($file->length() < 12) {
            throw InvalidFont::because('A font file is at least 12 bytes long.');
        }

        return match ($file->tag(0)) {
            "\0\1\0\0", 'true' => [Format::TrueType, [FaceReader::read(self::sfnt($file, 0, $budget))], null],
            'OTTO' => [Format::OpenType, [FaceReader::read(self::sfnt($file, 0, $budget))], null],
            'ttcf' => [Format::Collection, self::collection($file, $budget), null],
            'wOFF' => self::woff($file, $budget),
            'wOF2' => self::woff2($file, $brotli, $budget),
            default => throw InvalidFont::because('The file is neither TrueType, OpenType, a collection, WOFF nor WOFF2.'),
        };
    }

    /**
     * The tables of the `sfnt` whose directory starts at an offset; table offsets count from the start of the file.
     *
     * @return array<string, Binary>
     */
    private static function sfnt(Binary $file, int $offset, Budget $budget): array
    {
        if (!\in_array($file->tag($offset), ["\0\1\0\0", 'true', 'OTTO'], true)) {
            throw InvalidFont::because('A face of the collection is not an sfnt.');
        }

        $tables = [];

        for ($index = 0, $count = self::tableCount($file->uint16($offset + 4)); $index < $count; ++$index) {
            $at = $offset + 12 + 16 * $index;
            $tag = self::newTag($file->tag($at), $tables);
            $start = $file->uint32($at + 8);
            $length = $file->uint32($at + 12);

            if (!\in_array($tag, self::READ, true)) {
                $file->within($start, $length);
                $tables[$tag] = new Binary('');

                continue;
            }

            $budget->spend($length);
            $tables[$tag] = new Binary($file->slice($start, $length));
        }

        return $tables;
    }

    /**
     * @return non-empty-list<Font>
     */
    private static function collection(Binary $file, Budget $budget): array
    {
        $faces = [];
        $count = $file->uint32(8);

        if ($count > self::MAX_FACES) {
            throw InvalidFont::because('A collection has at most 64 faces.');
        }

        for ($index = 0; $index < $count; ++$index) {
            $faces[] = FaceReader::read(self::sfnt($file, $file->uint32(12 + 4 * $index), $budget));
        }

        return [] === $faces ? throw InvalidFont::because('The collection holds no face.') : $faces;
    }

    /**
     * @return array{Format, non-empty-list<Font>, ?string}
     */
    private static function woff(Binary $file, Budget $budget): array
    {
        $tables = [];
        $blocks = [];

        for ($index = 0, $count = self::tableCount($file->uint16(12)); $index < $count; ++$index) {
            $at = 44 + 20 * $index;
            $tag = self::newTag($file->tag($at), $tables);
            $offset = $file->uint32($at + 4);
            $length = $file->uint32($at + 8);
            $file->within($offset, $length);

            if (0 !== $length) {
                $blocks[] = [$offset, $length];
            }

            if (!\in_array($tag, self::READ, true)) {
                $tables[$tag] = new Binary('');

                continue;
            }

            $original = $file->uint32($at + 12);
            $budget->spend($original);
            $tables[$tag] = new Binary(self::inflate($file->slice($offset, $length), $original));
        }

        self::checkBlocks($blocks);
        $metadataLength = $file->uint32(28);

        if (0 !== $metadataLength) {
            $budget->spend($file->uint32(32));
        }

        $metadata = 0 === $metadataLength ? null : self::inflate($file->slice($file->uint32(24), $metadataLength), $file->uint32(32));

        return [Format::Woff, [FaceReader::read($tables)], $metadata];
    }

    /**
     * The number of tables a face lists, at most `MAX_TABLES`.
     */
    private static function tableCount(int $count): int
    {
        if ($count > self::MAX_TABLES) {
            throw InvalidFont::because('A face of the font has more than 256 tables.');
        }

        return $count;
    }

    /**
     * A tag the face has not listed yet.
     *
     * @param array<string, mixed> $tables
     */
    private static function newTag(string $tag, array $tables): string
    {
        if (\array_key_exists($tag, $tables)) {
            throw InvalidFont::because(\sprintf('The font has the table "%s" twice.', $tag));
        }

        return $tag;
    }

    /**
     * Checks that no two blocks of a WOFF file share bytes. Once sorted, each block starts at or after the end of the
     * one before it, which ends the furthest of all the blocks before it when none of them overlap.
     *
     * @param list<array{int, int}> $blocks the offset and length of each block that is not empty
     */
    private static function checkBlocks(array $blocks): void
    {
        sort($blocks);

        foreach (\array_slice($blocks, 1) as $before => [$offset]) {
            if ($offset < $blocks[$before][0] + $blocks[$before][1]) {
                throw InvalidFont::because('Two blocks of the WOFF file overlap.');
            }
        }
    }

    /**
     * A WOFF block: stored as it is when its lengths match, else compressed with zlib to its original length.
     */
    private static function inflate(string $stored, int $length): string
    {
        if (\strlen($stored) === $length) {
            return $stored;
        }

        $data = @gzuncompress($stored, $length);

        if (false === $data || \strlen($data) !== $length) {
            throw InvalidFont::because('A compressed WOFF block is corrupt or longer than it declares.');
        }

        return $data;
    }

    /**
     * WOFF2: the table directory, then every table compressed together with Brotli. The tables a WOFF2 encoder
     * transforms (`glyf`, `loca`, `hmtx`) carry no metadata: they are kept empty, which tells the outlines, rather than
     * rebuilt.
     *
     * @param (Closure(string, int): (string|false))|null $brotli
     *
     * @return array{Format, non-empty-list<Font>, ?string}
     */
    private static function woff2(Binary $file, ?Closure $brotli, Budget $budget): array
    {
        if ('ttcf' === $file->tag(4)) {
            throw UnsupportedFont::because('WOFF2 collections are not read.');
        }

        $directory = [];
        $at = 48;
        $total = 0;

        for ($index = 0, $count = self::tableCount($file->uint16(12)); $index < $count; ++$index) {
            $flags = $file->uint8($at);
            $known = $flags & 0x3F;
            $tag = self::newTag(63 === $known ? $file->tag($at + 1) : self::WOFF2_TAGS[$known], $directory);
            [$length, $at] = $file->base128($at + (63 === $known ? 5 : 1));
            $version = $flags >> 6;
            $transformed = \in_array($tag, ['glyf', 'loca'], true) ? 3 !== $version : 0 !== $version;

            if ($transformed) {
                [$length, $at] = $file->base128($at);
            }

            $directory[$tag] = [$total, $length];
            $total += $length;
        }

        if (!$brotli instanceof Closure) {
            throw UnsupportedFont::woff2WithoutBrotli();
        }

        $budget->spend($total);
        $data = self::unbrotli($brotli, $file->slice($at, $file->uint32(20)), $total);
        $tables = [];

        foreach ($directory as $tag => [$offset, $length]) {
            $tables[$tag] = new Binary(\in_array($tag, self::READ, true) ? substr($data, $offset, $length) : '');
        }

        $metadataLength = $file->uint32(32);

        if (0 !== $metadataLength) {
            $budget->spend($file->uint32(36));
        }

        $metadata = 0 === $metadataLength ? null : self::unbrotli($brotli, $file->slice($file->uint32(28), $metadataLength), $file->uint32(36));

        return [Format::Woff2, [FaceReader::read($tables)], $metadata];
    }

    /**
     * @param Closure(string, int): (string|false) $brotli
     */
    private static function unbrotli(Closure $brotli, string $compressed, int $length): string
    {
        $data = $brotli($compressed, $length);

        if (!\is_string($data) || \strlen($data) !== $length) {
            throw InvalidFont::because('A Brotli block of the WOFF2 file is corrupt or not as long as it declares.');
        }

        return $data;
    }
}
