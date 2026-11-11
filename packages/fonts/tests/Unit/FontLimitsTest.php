<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\FontFile;
use Phil\Fonts\Outlines;
use Phil\Fonts\Reader\Binary;
use Phil\Fonts\Reader\Budget;
use Phil\Fonts\Reader\FontReader;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The work a font file can cause is bounded: its size, its tables, its faces, and what it decompresses to.
 */
#[CoversClass(FontReader::class)]
#[CoversClass(Binary::class)]
#[CoversClass(Budget::class)]
final class FontLimitsTest extends TestCase
{
    private const int MIB = 1_048_576;

    public function testAFileUpToTheLimitIsRead(): void
    {
        $font = FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'Large Sans']), 'glyf' => '']);

        self::assertSame('Large Sans', FontFile::fromBytes(str_pad($font, 32 * self::MIB, "\0"))->face()->family());
    }

    public function testAFileOverTheLimitIsRefused(): void
    {
        $this->expectExceptionObject(InvalidFont::because('A font file is at most 32 MiB long.'));

        FontFile::fromBytes(str_pad(FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'Large Sans'])]), 32 * self::MIB + 1, "\0"));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tooMany(): iterable
    {
        yield 'sfnt tables' => ["\0\1\0\0".FontBuilder::u16(257).str_repeat("\0", 6), 'A face of the font has more than 256 tables.'];
        yield 'WOFF tables' => ['wOFF'."\0\1\0\0".FontBuilder::u32(44).FontBuilder::u16(257).str_repeat("\0", 30), 'A face of the font has more than 256 tables.'];
        yield 'WOFF2 tables' => ['wOF2'."\0\1\0\0".FontBuilder::u32(48).FontBuilder::u16(257).str_repeat("\0", 34), 'A face of the font has more than 256 tables.'];
        yield 'faces' => ['ttcf'.FontBuilder::u16(2).FontBuilder::u16(0).FontBuilder::u32(65), 'A collection has at most 64 faces.'];
    }

    #[DataProvider('tooMany')]
    public function testTooManyTablesOrFacesAreRefused(string $bytes, string $message): void
    {
        $this->expectExceptionObject(InvalidFont::because($message));

        FontFile::fromBytes($bytes, static fn (string $data): string => $data);
    }

    public function testTheLimitsOfTablesAndFacesAreReached(): void
    {
        $tables = ['name' => FontBuilder::names([1 => 'Many'])];

        for ($index = 1; $index < 256; ++$index) {
            $tables[\sprintf('t%03d', $index)] = '';
        }

        self::assertSame('Many', FontFile::fromBytes(FontBuilder::sfnt($tables))->face()->family());
        self::assertSame('Many', FontFile::fromBytes(FontBuilder::woff($tables))->face()->family());
        self::assertCount(64, FontFile::fromBytes(FontBuilder::collection(array_fill(0, 64, ['name' => FontBuilder::names([1 => 'Face'])])))->faces);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function duplicates(): iterable
    {
        $sfnt = FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'One']), 'cmap' => FontBuilder::names([1 => 'Two'])]);
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'One']), 'cmap' => FontBuilder::names([1 => 'Two'])]);

        yield 'sfnt' => [substr_replace($sfnt, 'name', 12 + 16, 4)];
        yield 'WOFF' => [substr_replace($woff, 'name', 44 + 20, 4)];
        yield 'WOFF2' => [FontBuilder::woff2([['name', FontBuilder::names([1 => 'One']), 0], ['name', FontBuilder::names([1 => 'Two']), 0]])];
    }

    #[DataProvider('duplicates')]
    public function testATableListedTwiceIsRefused(string $bytes): void
    {
        $this->expectExceptionObject(InvalidFont::because('The font has the table "name" twice.'));

        FontFile::fromBytes($bytes, static fn (string $data): string => $data);
    }

    public function testOverlappingWoffBlocksAreRefused(): void
    {
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'One']), 'cmap' => FontBuilder::names([1 => 'Two'])], compress: false);
        $first = substr($woff, 44 + 4, 4);

        $this->expectExceptionObject(InvalidFont::because('Two blocks of the WOFF file overlap.'));

        FontFile::fromBytes(substr_replace($woff, $first, 44 + 20 + 4, 4));
    }

    public function testAdjacentEmptyAndUnorderedWoffBlocksAreRead(): void
    {
        $name = FontBuilder::names([1 => 'One']);
        $woff = FontBuilder::woff(['name' => $name, 'glyf' => 'glyf', 'loca' => '', 'hmtx' => ''], compress: false);
        $nameOffset = substr($woff, 44 + 4, 4);
        $inside = FontBuilder::u32((\ord($nameOffset[2]) << 8 | \ord($nameOffset[3])) + 2);
        // The glyf block starts right where the name block ends, and the two empty blocks lie inside the name block.
        $woff = substr_replace($woff, FontBuilder::u32((\ord($nameOffset[2]) << 8 | \ord($nameOffset[3])) + \strlen($name)), 44 + 20 + 4, 4);
        $woff = substr_replace($woff, $inside, 44 + 40 + 4, 4);
        $woff = substr_replace($woff, $inside, 44 + 60 + 4, 4);

        self::assertSame('One', FontFile::fromBytes($woff)->face()->family());

        // The directory lists the blocks in another order than the file holds them.
        $unordered = substr($woff, 0, 44).substr($woff, 44 + 20, 20).substr($woff, 44, 20).substr($woff, 44 + 40);
        self::assertSame('One', FontFile::fromBytes($unordered)->face()->family());
    }

    public function testATableTheFaceDoesNotReadComesBeforeTheOthers(): void
    {
        $tables = ['glyf' => 'glyf', 'name' => FontBuilder::names([1 => 'After'])];

        self::assertSame('After', FontFile::fromBytes(FontBuilder::sfnt($tables))->face()->family());
        self::assertSame('After', FontFile::fromBytes(FontBuilder::woff($tables))->face()->family());
        self::assertSame(Outlines::TrueType, FontFile::fromBytes(FontBuilder::woff($tables))->face()->outlines);
    }

    public function testAWoffTableTheFaceDoesNotReadIsStillWithinTheFile(): void
    {
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'Outside']), 'glyf' => 'glyf'], compress: false);

        $this->expectExceptionObject(InvalidFont::because('The font file is truncated.'));

        FontFile::fromBytes(substr_replace($woff, FontBuilder::u32(\strlen($woff)), 44 + 20 + 8, 4));
    }

    public function testAWoffBlockDeclaredPastTheBudgetIsNotInflated(): void
    {
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'A long enough name to compress well, well, well, well'])]);

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes(substr_replace($woff, FontBuilder::u32(64 * self::MIB + 1), 44 + 12, 4));
    }

    public function testWoffBlocksArePaidFromOneBudget(): void
    {
        $woff = FontBuilder::woff(['cmap' => str_repeat("\0", 33 * self::MIB), 'name' => FontBuilder::names([1 => 'A long enough name to compress well, well, well, well'])]);
        // The original length of the name block, declared 32 MiB: the budget refuses it before it is inflated.
        $woff = substr_replace($woff, FontBuilder::u32(32 * self::MIB), 44 + 20 + 12, 4);

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes($woff);
    }

    public function testWoffMetadataIsPaidFromTheBudget(): void
    {
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'Meta'])], metadata: '<metadata version="1.0"/>');

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes(substr_replace($woff, FontBuilder::u32(64 * self::MIB), 32, 4));
    }

    public function testATableTheFaceDoesNotReadIsNotInflated(): void
    {
        $woff = FontBuilder::woff(['name' => FontBuilder::names([1 => 'Outlines']), 'glyf' => str_repeat('glyph', 100)]);
        // The offset of the glyf block, in the second directory entry.
        $offset = substr($woff, 44 + 20 + 4, 4);
        $corrupt = substr_replace($woff, 'XXXX', (\ord($offset[0]) << 24 | \ord($offset[1]) << 16 | \ord($offset[2]) << 8 | \ord($offset[3])) + 2, 4);

        self::assertSame(Outlines::TrueType, FontFile::fromBytes($corrupt)->face()->outlines);
    }

    public function testWoff2IsRefusedBeforeDecompressingPastTheBudget(): void
    {
        $woff2 = FontBuilder::woff2([['name', FontBuilder::names([1 => 'N']), 0]]);
        // The length of the name table, the base 128 number after its flags byte at 48, made 64 MiB and one byte.
        $woff2 = substr($woff2, 0, 49).FontBuilder::base128(64 * self::MIB + 1).substr($woff2, 50);

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes($woff2, static fn (): never => self::fail('Nothing was to be decompressed.'));
    }

    public function testWoff2MetadataIsPaidFromTheBudget(): void
    {
        $woff2 = FontBuilder::woff2([['name', FontBuilder::names([1 => 'N']), 0]], '<m/>');

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes(substr_replace($woff2, FontBuilder::u32(64 * self::MIB), 36, 4), static fn (string $data, int $length): string => $length < self::MIB ? $data : self::fail('The metadata was not to be decompressed.'));
    }

    public function testSlicedTablesArePaidFromTheBudget(): void
    {
        $size = 11 * self::MIB;
        $directory = "\0\1\0\0".FontBuilder::u16(6).str_repeat("\0", 6);

        foreach (['name', 'cmap', 'OS/2', 'head', 'post', 'fvar'] as $tag) {
            $directory .= $tag.FontBuilder::u32(0).FontBuilder::u32(0).FontBuilder::u32($size);
        }

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        FontFile::fromBytes(str_pad($directory, $size, "\0"));
    }

    public function testATableTheFaceDoesNotReadIsStillWithinTheFile(): void
    {
        $sfnt = FontBuilder::sfnt(['name' => FontBuilder::names([1 => 'Outside']), 'glyf' => 'glyf']);

        $this->expectExceptionObject(InvalidFont::because('The font file is truncated.'));

        FontFile::fromBytes(substr_replace($sfnt, FontBuilder::u32(\strlen($sfnt)), 12 + 16 + 12, 4));
    }

    public function testABudgetIsSpentToItsLastByte(): void
    {
        $budget = new Budget(10);
        $budget->spend(4);
        $budget->spend(6);
        $budget->spend(0);

        $this->expectExceptionObject(InvalidFont::because('The tables of the font file come to more than 64 MiB.'));

        $budget->spend(1);
    }
}
