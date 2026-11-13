<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Exception\InvalidFont;
use Phil\PagedMedia\Exception\PagedMediaException;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Bytes;
use Phil\PagedMedia\Tests\Unit\Fixture\TrueTypeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrueTypeFont::class)]
#[CoversClass(InvalidFont::class)]
final class TrueTypeFontTest extends TestCase
{
    public function testAFontIsReadWithItsNameMetricsAndCharacters(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0xE9 => 550])->build());

        self::assertSame('TestSans-Regular', $font->postScriptName());
        self::assertTrue($font->allowsSubsetting());
        self::assertSame([1, 2, 3, 0], $font->glyphs('ABé€'));
        self::assertSame([], $font->glyphs(''));
        self::assertEqualsWithDelta(13.0, $font->width('AB', 10), 0.0001);
        self::assertEqualsWithDelta(5.0, $font->width('€', 10), 0.0001, 'A missing character is as wide as the missing glyph.');
        self::assertSame(['boundingBox' => [-100, -200, 1100, 900], 'ascent' => 800, 'descent' => -200, 'capHeight' => 700, 'italicAngle' => 0.0, 'fixedPitch' => false], $font->descriptor());
    }

    public function testMetricsAreScaledFromTheUnitsPerEm(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 1229])->unitsPerEm(2048)->build());

        self::assertSame(600, $font->advance(1));
        self::assertSame(244, $font->advance(0));
        self::assertEqualsWithDelta(6.0, $font->width('A', 10), 0.0001);
        self::assertSame(['boundingBox' => [-49, -98, 537, 439], 'ascent' => 391, 'descent' => -98, 'capHeight' => 342, 'italicAngle' => 0.0, 'fixedPitch' => false], $font->descriptor());
    }

    public function testAGlyphBeyondTheMetricsTakesTheLastAdvance(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700])->build());

        self::assertSame(700, $font->advance(9));
    }

    public function testCharactersBeyondTheBasicPlaneAreReadFromFormat12(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x1F600 => 1000])->format12()->build());

        self::assertSame([1, 2, 0], $font->glyphs('A😀B'));
    }

    public function testTheDescriptorReadsTheItalicAngleThePitchAndTheCapHeight(): void
    {
        $italic = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->italic(-11.25)->fixedPitch()->build());
        $oldOs2 = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->os2Version(1)->build());
        $bare = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->withoutOs2()->withoutPost()->named(null)->build());

        self::assertSame([-11.25, true], [$italic->descriptor()['italicAngle'], $italic->descriptor()['fixedPitch']]);
        self::assertSame(800, $oldOs2->descriptor()['capHeight'], 'Without a cap height, the ascent stands for it.');
        self::assertSame([800, 0.0, false, 'Embedded'], [$bare->descriptor()['capHeight'], $bare->descriptor()['italicAngle'], $bare->descriptor()['fixedPitch'], $bare->postScriptName()]);
        self::assertTrue($bare->allowsSubsetting());
    }

    public function testThePostScriptNameKeepsTheLettersDigitsAndHyphensItAllows(): void
    {
        self::assertSame('MyFont-Boldv2', TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->named('My Font-Bold (v2)!')->build())->postScriptName());
        self::assertSame('Embedded', TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->named('***')->build())->postScriptName());
    }

    public function testASubsetKeepsTheGlyphsUsedTheMissingGlyphAndTheComponents(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0x44 => 500, 0x45 => 500, 0x46 => 500])->composite(0x43, 650, [0x41, 0x42, 0x44, 0x45])->build();
        $font = TrueTypeFont::fromBytes($bytes);
        $subset = $font->subset([3, 3]);

        self::assertLessThan(\strlen($bytes), \strlen($subset));
        self::assertSame(['cvt ', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp'], self::tables($subset));
        self::assertSame([0, 36, 36, 52, 36, 36, 0], $this->glyphLengths($subset), 'The composite and its four components are kept, padded to four bytes, and the glyph used by none is emptied.');
        self::assertSame(1, Bytes::readUint16($subset, self::tableOffset($subset, 'head') + 50), 'The loca table is long.');
        self::assertSame(0, Bytes::readUint32($subset, self::tableOffset($subset, 'head') + 8), 'The checksum adjustment is cleared.');
        self::assertSame([0, 0, 0, 0, 0, 0, 0], array_map(static fn (int $tag): int => $tag % 4, array_map(static fn (string $tag): int => self::tableOffset($subset, $tag), self::tables($subset))), 'Every table starts on a four-byte boundary.');
    }

    public function testATableIsChecksummedOverItsPaddedWords(): void
    {
        $subset = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build())->subset([1]);

        foreach (self::tables($subset) as $index => $tag) {
            $entry = 12 + 16 * $index;
            $offset = Bytes::readUint32($subset, $entry + 8);
            $length = Bytes::readUint32($subset, $entry + 12);
            $data = substr($subset, $offset, $length);
            $sum = 0;

            foreach (str_split($data.str_repeat("\0", (4 - $length % 4) % 4), 4) as $word) {
                $sum = ($sum + Bytes::readUint32($word, 0)) & 0xFFFFFFFF;
            }

            self::assertSame($sum, Bytes::readUint32($subset, $entry + 4), $tag);
        }

        self::assertSame([7, 64, 2, 48], [Bytes::readUint16($subset, 4), Bytes::readUint16($subset, 6), Bytes::readUint16($subset, 8), Bytes::readUint16($subset, 10)]);
    }

    public function testASubsetReadsAShortOrALongLoca(): void
    {
        $short = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->build());
        $long = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->longLoca()->build());

        self::assertSame($this->glyphLengths($short->subset([2])), $this->glyphLengths($long->subset([2])));
        self::assertSame([0, 0, 36], $this->glyphLengths($long->subset([2])));
    }

    public function testAGlyphOutsideTheFontIsIgnored(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());

        self::assertSame([0, 0], $this->glyphLengths($font->subset([-1, 7])));
    }

    public function testAFontThatForbidsSubsettingIsEmbeddedWhole(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->embedding(0x0100 | 0x0008)->build();
        $font = TrueTypeFont::fromBytes($bytes);

        self::assertFalse($font->allowsSubsetting());
        self::assertSame($bytes, $font->subset([1]));
    }

    public function testAFontIsReadFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'font');
        self::assertIsString($path);

        try {
            file_put_contents($path, TrueTypeBuilder::with([0x41 => 600])->version('true')->build());
            self::assertSame('TestSans-Regular', TrueTypeFont::fromFile($path)->postScriptName());
        } finally {
            unlink($path);
        }
    }

    public function testAnAdvanceIsRoundedToTheNearestThousandth(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 1230])->unitsPerEm(2048)->build());

        self::assertSame(601, $font->advance(1), '1230 / 2048 is 600.59 thousandths.');
    }

    public function testTheGlyphsPastTheMetricsShareTheLastAdvance(): void
    {
        $one = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700])->hMetrics(1)->build());
        $none = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700])->hMetrics(0)->build());

        self::assertSame([500, 500, 500], [$one->advance(0), $one->advance(1), $one->advance(2)]);
        self::assertSame([500, 500], [$none->advance(0), $none->advance(1)], 'A font declaring no advance still has the first one.');
    }

    public function testASubsetKeepsTheBytesOfEachGlyphItKeeps(): void
    {
        // A simple glyph of 35 bytes, padded to 36 in the subset.
        $odd = pack('nnnnn', 1, 0, 0, 1, 1).str_repeat("\x07", 25);
        $bytes = TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0x43 => 800])->glyph(0x44, 500, $odd)->missingGlyphOutline()->longLoca()->table('fpgm', "\1\2\3\4\5")->build();
        $subset = TrueTypeFont::fromBytes($bytes)->subset([4, 2]);

        self::assertSame([36, 0, 36, 0, 36], $this->glyphLengths($subset));
        self::assertSame(24, $this->tableLength($subset, 'loca'), 'The loca table has one offset per glyph, and the end.');

        foreach ([0, 2, 4] as $glyph) {
            $data = $this->glyphData($bytes, $glyph);
            self::assertSame($data.str_repeat("\0", 36 - \strlen($data)), substr($subset, self::tableOffset($subset, 'glyf') + Bytes::readUint32($subset, self::tableOffset($subset, 'loca') + 4 * $glyph), 36), 'Glyph '.$glyph.' is kept as it is, then padded.');
        }

        self::assertSame([0, 0, 0, 36], $this->glyphLengths(TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0x43 => 800])->longLoca()->build())->subset([3, 3])), 'A glyph asked twice is kept once.');
        self::assertSame([36, 36, 0, 0, 36], $this->glyphLengths(TrueTypeFont::fromBytes($bytes)->subset([1, 1, 4])), 'The missing glyph is kept after a glyph asked twice.');
    }

    public function testASubsetCopiesTheHeadWithALongLocaAndNoChecksumAdjustment(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600])->build();
        $subset = TrueTypeFont::fromBytes($bytes)->subset([1]);
        $head = substr($bytes, self::tableOffset($bytes, 'head'), $this->tableLength($bytes, 'head'));

        self::assertSame(substr_replace(substr_replace($head, "\0\0\0\0", 8, 4), "\0\1", 50, 2), substr($subset, self::tableOffset($subset, 'head'), $this->tableLength($subset, 'head')));
    }

    public function testASubsetOfEightTablesIsLaidOutEndToEnd(): void
    {
        $subset = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->table('fpgm', "\1\2\3\4\5")->build())->subset([1]);
        $tables = self::tables($subset);
        $end = 12 + 16 * \count($tables);

        self::assertSame("\0\1\0\0", substr($subset, 0, 4));
        self::assertSame(['cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp'], $tables);
        self::assertSame([8, 128, 3, 0], [Bytes::readUint16($subset, 4), Bytes::readUint16($subset, 6), Bytes::readUint16($subset, 8), Bytes::readUint16($subset, 10)]);
        self::assertSame("\1\2\3\4\5", substr($subset, self::tableOffset($subset, 'fpgm'), $this->tableLength($subset, 'fpgm')));

        foreach ($tables as $index => $tag) {
            self::assertSame($end, self::tableOffset($subset, $tag), 'Table '.$tag.' follows the previous one and its padding.');
            $end += $this->tableLength($subset, $tag) + (4 - $this->tableLength($subset, $tag) % 4) % 4;
            $entry = 12 + 16 * $index;
            $data = substr($subset, self::tableOffset($subset, $tag), $this->tableLength($subset, $tag));
            $sum = 0;

            foreach (str_split($data.str_repeat("\0", (4 - \strlen($data) % 4) % 4), 4) as $word) {
                $sum = ($sum + Bytes::readUint32($word, 0)) & 0xFFFFFFFF;
            }

            self::assertSame($sum, Bytes::readUint32($subset, $entry + 4), $tag);
        }

        self::assertSame($end, \strlen($subset));
    }

    public function testAGlyphJustPastTheFontIsIgnored(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->longLoca()->last('loca')->build());

        self::assertSame([0, 0, 0], $this->glyphLengths($font->subset([3, 0x7FFF, -1])), 'The loca table ends the file: reading past its glyphs would be reading past the file.');
    }

    public function testACompositeGlyphThatContainsItselfIsKeptOnce(): void
    {
        $composite = pack('nnnnn', 0xFFFF, 0, 0, 0, 0).pack('nnCC', 0x0020, 2, 0, 0).pack('nnCC', 0, 2, 0, 0);
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->glyph(0x42, 600, $composite)->longLoca()->build());
        set_time_limit(10);

        try {
            self::assertSame([0, 0, 24], $this->glyphLengths($font->subset([2])));
        } finally {
            set_time_limit(0);
        }
    }

    public function testACompositeGlyphIsReadComponentByComponent(): void
    {
        // Each component's arguments and transformation take the room their flags give them; the last one announces
        // no other, and what follows it is not a component.
        $composite = pack('nnnnn', 0xFFFF, 0, 0, 0, 0)
            .pack('nnnn', 0x0021, 1, 0, 0)
            .pack('nnCC', 0x00A0, 2, 0, 0).pack('nnnn', 0x4000, 0, 0, 0x4000)
            .pack('nnnn', 0x00A1, 3, 0, 0).pack('nnnn', 0x4000, 0, 0, 0x4000)
            .pack('nnCC', 0x0028, 4, 0, 0).pack('n', 0x4000)
            .pack('nnCC', 0x0060, 5, 0, 0).pack('nn', 0x4000, 0x4000)
            .pack('nnnn', 0x0001, 6, 0, 0)
            .pack('nn', 0, 7);
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600, 0x43 => 600, 0x44 => 600, 0x45 => 600, 0x46 => 600, 0x47 => 600])->glyph(0x48, 600, $composite)->longLoca()->build());

        self::assertSame([0, 36, 36, 36, 36, 36, 36, 0, 80], $this->glyphLengths($font->subset([8])));
    }

    public function testTheSignOfTheNumberOfContoursTellsACompositeGlyph(): void
    {
        $composite = pack('nnnnn', 0x8000, 0, 0, 0, 0).pack('nnCC', 0, 1, 0, 0);
        $simple = pack('nnnnn', 0x7FFF, 0, 0, 0, 0).pack('nnCC', 0, 2, 0, 0);
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->glyph(0x43, 600, $composite)->glyph(0x44, 600, $simple)->longLoca()->build());

        self::assertSame([0, 36, 0, 16, 0], $this->glyphLengths($font->subset([3])));
        self::assertSame([0, 0, 0, 0, 16], $this->glyphLengths($font->subset([4])));
    }

    public function testATruncatedCompositeGlyphKeepsTheComponentsItHasRoomFor(): void
    {
        $header = pack('nnnnn', 0xFFFF, 0, 0, 0, 0).pack('nnCC', 0x0020, 1, 0, 0);
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->glyph(0x43, 600, $header."\0\0\0")->glyph(0x44, 600, $header.pack('nn', 0, 2))->longLoca()->build());

        self::assertSame([0, 36, 0, 20, 0], $this->glyphLengths($font->subset([3])), 'Three bytes are too few for another component.');
        self::assertSame([0, 36, 36, 0, 20], $this->glyphLengths($font->subset([4])), 'Four bytes give its flags and its glyph.');
    }

    public function testTheCharacterMapIsFoundAmongItsEncodingRecords(): void
    {
        $font = static fn (string $cmap): TrueTypeFont => TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600, 0x43 => 600])->table('cmap', $cmap)->build());
        $format4 = static fn (int $glyph): string => TrueTypeBuilder::format4Subtable([[0x41, 0x41, $glyph - 0x41, null]]);
        $format12 = static fn (int $glyph): string => TrueTypeBuilder::format12Subtable([[0x41, 0x41, $glyph]]);

        self::assertSame([1], $font(TrueTypeBuilder::cmapTable([[1, 0, $format4(2)], [3, 1, $format4(1)]]))->glyphs('A'), 'The Unicode record comes second.');
        self::assertSame([1], $font(TrueTypeBuilder::cmapTable([[3, 1, $format4(1)]], 1))->glyphs('A'), 'The version of the table is not read.');
        self::assertSame([1], $font(TrueTypeBuilder::cmapTable([[3, 1, $format4(1)]], hidden: [3, 10, $format12(2)]))->glyphs('A'), 'Only the records counted are read.');
        self::assertSame([2], $font(TrueTypeBuilder::cmapTable([[0, 4, $format12(2)]]))->glyphs('A'), 'Format 12 under the Unicode platform.');
        self::assertSame([3], $font(TrueTypeBuilder::cmapTable([[0, 4, $format12(2)], [3, 10, $format12(3)]]))->glyphs('A'), 'Format 12 under Windows first.');
        self::assertSame([2], $font(TrueTypeBuilder::cmapTable([[0, 3, $format4(2)]]))->glyphs('A'), 'Format 4 under the Unicode platform.');
        self::assertSame([3], $font(TrueTypeBuilder::cmapTable([[0, 3, $format4(2)], [3, 1, $format4(3)]]))->glyphs('A'), 'Format 4 under Windows first.');
    }

    public function testFormat12ReadsItsGroupsUpToTheLastCodePoint(): void
    {
        $font = static fn (string $subtable): TrueTypeFont => TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600, 0x43 => 600])->table('cmap', TrueTypeBuilder::cmapTable([[3, 10, $subtable]]))->build());

        self::assertSame([1, 2, 0], $font(TrueTypeBuilder::format12Subtable([[0x10FFFE, 0xFFFFFFFF, 1]]))->glyphs("\u{10FFFE}\u{10FFFF}A"));
        // The subtable's length is 28 and its language 30: read as a group, they would map the code points 28 to 30.
        self::assertSame([0, 0, 0, 1], $font(TrueTypeBuilder::format12Subtable([[0x41, 0x41, 1]], 30))->glyphs("\x1C\x1D\x1EA"));
    }

    public function testFormat4ReadsEachSegmentByDeltaOrThroughItsGlyphs(): void
    {
        $font = static fn (string $subtable): TrueTypeFont => TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600, 0x43 => 600])->table('cmap', TrueTypeBuilder::cmapTable([[3, 1, $subtable]]))->build());

        self::assertSame([0, 1, 2, 3], TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600, 0x43 => 600])->build())->glyphs("\0ABC"));
        self::assertSame([0, 1, 2, 3], $font(TrueTypeBuilder::format4Subtable([[0x41, 0x41, 0, [1]], [0x42, 0x43, 2 - 0x42, null]]))->glyphs("\0ABC"), 'The first segment goes through its glyphs.');
        self::assertSame([2, 0, 3, 0], $font(TrueTypeBuilder::format4Subtable([[0x61, 0x63, 1, [1, 0, 2]]]))->glyphs('abcd'), 'A glyph read from the array takes the delta, unless it is the missing glyph.');
        self::assertSame([3, 2, 1], $font(TrueTypeBuilder::format4Subtable([[0x61, 0x63, 0, [3, 2, 1]]]))->glyphs('abc'));
    }

    public function testThePostScriptNameIsTheFirstRecordSixReadable(): void
    {
        $font = static fn (string $name): TrueTypeFont => TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->table('name', $name)->build());
        $utf16 = static fn (string $text): string => mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');

        self::assertSame('PsName', $font(pack('nnn', 0, 2, 30).pack('nnnnnn', 3, 1, 0x409, 1, 6, 0).pack('nnnnnn', 3, 1, 0x409, 6, 12, 6).$utf16('Fam').$utf16('PsName'))->postScriptName(), 'A string is found at its offset in the storage.');
        self::assertSame('MacName', $font(pack('nnn', 0, 1, 18).pack('nnnnnn', 1, 0, 0, 6, 7, 0).'MacName')->postScriptName(), 'A Macintosh name is in Mac Roman.');
        self::assertSame('First', $font(pack('nnn', 0, 2, 30).pack('nnnnnn', 1, 0, 0, 6, 5, 0).pack('nnnnnn', 3, 1, 0x409, 6, 12, 5).'First'.$utf16('Second'))->postScriptName());
        // Read as a record, the six bytes before the table and its first six would be a name 6 of two bytes at 30.
        self::assertSame('Real', $font(pack('nnn', 6, 2, 30).pack('nnnnnn', 3, 1, 0x409, 6, 8, 0).pack('nnnnnn', 3, 1, 0x409, 1, 6, 8).$utf16('Real').$utf16('Fam').str_repeat("\0", 16)."\0X")->postScriptName());
        // The storage starts with what would be a name 6 record, were it counted.
        self::assertSame('Embedded', $font(pack('nnn', 0, 1, 18).pack('nnnnnn', 3, 1, 0x409, 1, 6, 12).pack('nnnnnn', 1, 0, 0, 6, 4, 18).$utf16('Fam').'Fake')->postScriptName());
    }

    public function testTheCapHeightIsReadFromAnOs2TableOfVersion2AndNinetyBytes(): void
    {
        $capHeight = static fn (TrueTypeBuilder $builder): int => TrueTypeFont::fromBytes($builder->build())->descriptor()['capHeight'];

        self::assertSame(700, $capHeight(TrueTypeBuilder::with([0x41 => 600])->os2Version(2)));
        self::assertSame(700, $capHeight(TrueTypeBuilder::with([0x41 => 600])->os2Version(2)->os2Length(90)));
        self::assertSame(800, $capHeight(TrueTypeBuilder::with([0x41 => 600])->os2Version(2)->os2Length(86)));
        self::assertSame(800, $capHeight(TrueTypeBuilder::with([0x41 => 600])->os2Version(1)->os2Length(96)));
    }

    public function testASignedValueWithItsTopBitSetIsNegative(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600])->build();
        $bytes = substr_replace($bytes, pack('nn', 0x8000, 0x7FFF), self::tableOffset($bytes, 'head') + 36, 4);

        self::assertSame([-32768, 32767], \array_slice(TrueTypeFont::fromBytes($bytes)->descriptor()['boundingBox'], 0, 2));
    }

    public function testTheWholeFixedPitchFieldIsRead(): void
    {
        self::assertTrue(TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->fixedPitch(0x01000000)->build())->descriptor()['fixedPitch']);
    }

    public function testATableMayEndTheFile(): void
    {
        $short = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->last('loca')->build());
        $long = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->longLoca()->last('loca')->build());

        self::assertSame([0, 0, 36], $this->glyphLengths($short->subset([2])));
        self::assertSame([0, 0, 36], $this->glyphLengths($long->subset([2])));
    }

    /**
     * @return iterable<string, array{TrueTypeBuilder}>
     */
    public static function truncatedLocas(): iterable
    {
        yield 'short' => [TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->last('loca', 1)];
        yield 'long' => [TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->longLoca()->last('loca', 1)];
        yield 'long by three bytes' => [TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->longLoca()->last('loca', 3)];
    }

    #[DataProvider('truncatedLocas')]
    public function testAValueCutByTheEndOfTheFileIsTruncation(TrueTypeBuilder $builder): void
    {
        $font = TrueTypeFont::fromBytes($builder->build());

        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('The font file is truncated.');

        $font->subset([2]);
    }

    public function testAFontFileThatIsADirectoryIsRefused(): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('cannot be read.');

        TrueTypeFont::fromFile(__DIR__);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFonts(): iterable
    {
        $font = TrueTypeBuilder::with([0x41 => 600]);
        $valid = $font->build();

        yield 'too short' => ["\0\1\0\0", 'A font file is at least 12 bytes long.'];
        yield 'a directory of no table' => ["\0\1\0\0".str_repeat("\0", 8), 'The font has no "head" table.'];
        yield 'PostScript outlines' => [TrueTypeBuilder::with([0x41 => 600])->version('OTTO')->build(), 'The font has PostScript outlines (CFF); only TrueType outlines can be embedded.'];
        yield 'not a font' => [str_repeat('x', 40), 'The file is not a TrueType font.'];
        yield 'no glyf table' => [TrueTypeBuilder::with([0x41 => 600])->without('glyf')->build(), 'The font has no "glyf" table.'];
        yield 'no head table' => [TrueTypeBuilder::with([0x41 => 600])->without('head')->build(), 'The font has no "head" table.'];
        yield 'a table past the end' => [substr($valid, 0, -40), 'A table of the font runs past the end of the file.'];
        yield 'a truncated directory' => [substr($valid, 0, 20), 'The font file is truncated.'];
        yield 'no units per em' => [TrueTypeBuilder::with([0x41 => 600])->unitsPerEm(0)->build(), 'The font declares no units per em.'];
        yield 'a restricted licence' => [TrueTypeBuilder::with([0x41 => 600])->embedding(0x0002)->build(), 'The licence of the font "TestSans-Regular" forbids embedding it in a document.'];
        yield 'bitmap embedding only' => [TrueTypeBuilder::with([0x41 => 600])->embedding(0x0200)->build(), 'The licence of the font "TestSans-Regular" forbids embedding it in a document.'];
        yield 'no Unicode map' => [self::withoutUnicodeMap($valid), 'The font has no Unicode character map.'];
    }

    #[DataProvider('invalidFonts')]
    public function testAnInvalidFontIsRefused(string $bytes, string $message): void
    {
        try {
            TrueTypeFont::fromBytes($bytes);
            self::fail('The font is refused.');
        } catch (InvalidFont $invalidFont) {
            self::assertSame($message, $invalidFont->getMessage());
            self::assertInstanceOf(PagedMediaException::class, $invalidFont);
        }
    }

    public function testAFontFileThatCannotBeReadIsRefused(): void
    {
        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('The font file "/no/such/font.ttf" cannot be read.');

        TrueTypeFont::fromFile('/no/such/font.ttf');
    }

    public function testAnOffsetPastTheEndOfTheFileIsTruncation(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600])->build();
        // The cmap's subtable offset points past the end of the file.
        $cmap = self::tableOffset($bytes, 'cmap');
        $broken = substr_replace($bytes, pack('N', 0x00FFFFFF), $cmap + 8, 4);

        $this->expectException(InvalidFont::class);
        $this->expectExceptionMessageIsOrContains('The font file is truncated.');

        TrueTypeFont::fromBytes($broken);
    }

    /**
     * The font with its character map's subtable relabelled as a Macintosh Roman one, which the library does not read.
     */
    private static function withoutUnicodeMap(string $bytes): string
    {
        $cmap = self::tableOffset($bytes, 'cmap');

        return substr_replace($bytes, pack('nn', 1, 0), $cmap + 4, 4);
    }

    /**
     * @return list<string>
     */
    private static function tables(string $font): array
    {
        $tags = [];

        for ($index = 0, $count = Bytes::readUint16($font, 4); $index < $count; ++$index) {
            $tags[] = substr($font, 12 + 16 * $index, 4);
        }

        return $tags;
    }

    private static function tableOffset(string $font, string $tag): int
    {
        $index = array_search($tag, self::tables($font), true);
        self::assertIsInt($index);

        return Bytes::readUint32($font, 12 + 16 * $index + 8);
    }

    private function tableLength(string $font, string $tag): int
    {
        $index = array_search($tag, self::tables($font), true);
        self::assertIsInt($index);

        return Bytes::readUint32($font, 12 + 16 * $index + 12);
    }

    /**
     * A glyph's bytes in a font with a long loca table.
     */
    private function glyphData(string $font, int $glyph): string
    {
        $loca = self::tableOffset($font, 'loca');
        $start = Bytes::readUint32($font, $loca + 4 * $glyph);

        return substr($font, self::tableOffset($font, 'glyf') + $start, Bytes::readUint32($font, $loca + 4 * $glyph + 4) - $start);
    }

    /**
     * @return list<int> each glyph's length in a font with a long loca table
     */
    private function glyphLengths(string $font): array
    {
        $loca = self::tableOffset($font, 'loca');
        $glyphs = Bytes::readUint16($font, self::tableOffset($font, 'maxp') + 4);
        $lengths = [];

        for ($glyph = 0; $glyph < $glyphs; ++$glyph) {
            $lengths[] = Bytes::readUint32($font, $loca + 4 * $glyph + 4) - Bytes::readUint32($font, $loca + 4 * $glyph);
        }

        return $lengths;
    }
}
