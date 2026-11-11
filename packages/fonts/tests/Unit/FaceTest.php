<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Axis;
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\Font;
use Phil\Fonts\FontFile;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Licence\Licence;
use Phil\Fonts\Name\NameId;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;
use Phil\Fonts\Reader\Binary;
use Phil\Fonts\Reader\FaceReader;
use Phil\Fonts\Reader\NameTable;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FaceReader::class)]
#[CoversClass(Font::class)]
#[CoversClass(NameTable::class)]
#[CoversClass(Names::class)]
#[CoversClass(NameRecord::class)]
#[CoversClass(Axis::class)]
final class FaceTest extends TestCase
{
    /**
     * @param array<string, string> $tables
     */
    private function face(array $tables): Font
    {
        return FontFile::fromBytes(FontBuilder::sfnt(['name' => FontBuilder::names([]), ...$tables]))->face();
    }

    public function testReadsEveryNameOfTheFace(): void
    {
        $names = [
            0 => 'Copyright 2026 Example', 1 => 'Example', 2 => 'Bold', 4 => 'Example Bold', 5 => 'Version 1.002',
            6 => 'Example-Bold', 7 => 'Example is a trademark', 8 => 'Example Foundry', 9 => 'A. Designer',
            11 => 'https://example.com', 12 => 'https://example.com/designer', 13 => 'Licensed under the SIL Open Font License, Version 1.1',
            14 => 'https://openfontlicense.org',
        ];
        $face = $this->face(['name' => FontBuilder::names($names)]);

        self::assertSame('Example', $face->family());
        self::assertSame('Bold', $face->subfamily());
        self::assertSame('Example Bold', $face->fullName());
        self::assertSame('Example-Bold', $face->postScriptName());
        self::assertSame('Version 1.002', $face->version());
        self::assertSame('Copyright 2026 Example', $face->copyright());
        self::assertSame('Example is a trademark', $face->trademark());
        self::assertSame('Example Foundry', $face->manufacturer());
        self::assertSame('A. Designer', $face->designer());
        self::assertSame('https://example.com', $face->vendorUrl());
        self::assertSame('https://example.com/designer', $face->designerUrl());
        self::assertEquals(new Licence('Licensed under the SIL Open Font License, Version 1.1', 'https://openfontlicense.org'), $face->licence());
    }

    public function testPrefersTheTypographicFamilyAndSubfamily(): void
    {
        $face = $this->face(['name' => FontBuilder::names([1 => 'Example Light', 2 => 'Regular', 16 => 'Example', 17 => 'Light'])]);

        self::assertSame('Example', $face->family());
        self::assertSame('Light', $face->subfamily());
    }

    public function testAFaceWithoutNamesHasEmptyFamilies(): void
    {
        $face = $this->face([]);

        self::assertSame('', $face->family());
        self::assertSame('', $face->subfamily());
        self::assertNull($face->fullName());
        self::assertNull($face->licence()->description);
        self::assertNull($face->licence()->url);
    }

    public function testChoosesTheNameAReaderExpects(): void
    {
        $utf16 = static fn (string $text): string => mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        $names = NameTable::read(new Binary(FontBuilder::name([
            [1, 0, 0, 9, 'Designer'],
            [1, 0, 0, 1, 'Mac'],
            [0, 3, 0, 1, $utf16('Unicode')],
            [3, 1, 0x040C, 1, $utf16('Français')],
            [3, 1, 0x0409, 1, ''],
            [1, 0, 0, 2, 'Mac only'],
            [0, 4, 0, 2, $utf16('Unicode then')],
            [3, 10, 0x0407, 4, $utf16('Deutsch')],
            [3, 1, 0x0409, 4, $utf16('English')],
            [3, 0, 0x0409, 6, $utf16('Symbol')],
            [3, 2, 0x0409, 7, 'Shift JIS'],
            [1, 1, 11, 7, 'Japanese Mac'],
            [2, 0, 0, 7, 'ISO'],
        ])));

        self::assertSame('Français', $names->get(NameId::Family));
        self::assertSame('Unicode then', $names->get(NameId::Subfamily));
        self::assertSame('English', $names->get(NameId::FullName));
        self::assertSame('Symbol', $names->get(NameId::PostScriptName));
        self::assertNull($names->get(NameId::Trademark));
        self::assertSame(['Mac', 'Unicode', 'Français', ''], array_map(static fn (NameRecord $record): string => $record->value, $names->all(NameId::Family)));
        self::assertEquals(new NameRecord(3, 1, 0x040C, 1, 'Français'), $names->all(NameId::Family)[2]);
        self::assertCount(10, $names->records);
        self::assertSame('Mac only', new Names([new NameRecord(1, 0, 0, 2, 'Mac only'), new NameRecord(1, 0, 5, 2, 'Other')])->get(NameId::Subfamily));
    }

    public function testDecodesMacRoman(): void
    {
        $names = NameTable::read(new Binary(FontBuilder::name([[1, 0, 0, 1, "Caf\x8E \x80\xA9\xCA\xD2\xF0\xFF"]])));

        self::assertSame("Café Ä©\u{00A0}“\u{F8FF}ˇ", $names->get(NameId::Family));
    }

    public function testReadsWeightWidthStyleAndEmbeddingFromOs2(): void
    {
        $face = $this->face(['OS/2' => FontBuilder::os2(weight: 300, width: 3, fsType: 0x0104, selection: 0x0001), 'head' => FontBuilder::head(macStyle: 1)]);

        self::assertSame(300, $face->weight);
        self::assertSame(3, $face->widthClass);
        self::assertSame(FontStyle::Italic, $face->style);
        self::assertEquals(new EmbeddingPermissions(0x0104), $face->embedding);
        self::assertSame([300, 300], $face->weightRange());
        self::assertSame([75.0, 75.0], $face->stretchRange());
    }

    /**
     * @return iterable<string, array{int, int, int, int, FontStyle}>
     */
    public static function os2Values(): iterable
    {
        yield 'oblique wins over italic' => [1, 1, 0x0201, 1, FontStyle::Oblique];
        yield 'weight and width at their lowest' => [1, 1, 0x0040, 1, FontStyle::Normal];
        yield 'weight and width at their highest' => [1000, 9, 0x0020, 1000, FontStyle::Normal];
        yield 'weight zero' => [0, 5, 0, 400, FontStyle::Normal];
        yield 'weight past 1000' => [1001, 5, 0, 400, FontStyle::Normal];
    }

    #[DataProvider('os2Values')]
    public function testKeepsOs2ValuesInTheirRanges(int $weight, int $width, int $selection, int $expectedWeight, FontStyle $style): void
    {
        $face = $this->face(['OS/2' => FontBuilder::os2(weight: $weight, width: $width, selection: $selection)]);

        self::assertSame($expectedWeight, $face->weight);
        self::assertSame($width, $face->widthClass);
        self::assertSame($style, $face->style);
    }

    public function testAWidthClassOutOfRangeIsNormal(): void
    {
        self::assertSame(5, $this->face(['OS/2' => FontBuilder::os2(width: 0)])->widthClass);
        self::assertSame(5, $this->face(['OS/2' => FontBuilder::os2(width: 10)])->widthClass);
    }

    public function testAShortOs2TableHasNoSelectionBits(): void
    {
        self::assertSame(FontStyle::Normal, $this->face(['OS/2' => FontBuilder::os2(selection: 1, length: 63)])->style);
        self::assertSame(FontStyle::Italic, $this->face(['OS/2' => FontBuilder::os2(selection: 1, length: 64)])->style);
        self::assertSame(0x0008, $this->face(['OS/2' => FontBuilder::os2(fsType: 8, length: 10)])->embedding->fsType);
    }

    public function testFallsBackOnTheHeadStyleWithoutOs2(): void
    {
        $boldItalic = $this->face(['head' => FontBuilder::head(macStyle: 3)]);
        self::assertSame(700, $boldItalic->weight);
        self::assertSame(FontStyle::Italic, $boldItalic->style);
        self::assertSame(5, $boldItalic->widthClass);
        self::assertSame(0, $boldItalic->embedding->fsType);

        $italic = $this->face(['head' => FontBuilder::head(macStyle: 2)]);
        self::assertSame(400, $italic->weight);
        self::assertSame(FontStyle::Italic, $italic->style);

        $bold = $this->face(['head' => FontBuilder::head(macStyle: 1)]);
        self::assertSame(700, $bold->weight);
        self::assertSame(FontStyle::Normal, $bold->style);

        $plain = $this->face([]);
        self::assertSame(400, $plain->weight);
        self::assertSame(FontStyle::Normal, $plain->style);
    }

    public function testReadsTheItalicAngleAndFixedPitchFromPost(): void
    {
        $face = $this->face(['post' => FontBuilder::post(angle: -12, fraction: 0x8000, fixedPitch: 1)]);

        self::assertSame(-11.5, $face->italicAngle);
        self::assertTrue($face->fixedPitch);
        self::assertFalse($this->face(['post' => FontBuilder::post()])->fixedPitch);
        self::assertTrue($this->face(['post' => FontBuilder::post(fixedPitch: 0x01000000)])->fixedPitch);
        self::assertSame(0.0, $this->face([])->italicAngle);
        self::assertFalse($this->face([])->fixedPitch);
    }

    public function testReadsTheVariationAxes(): void
    {
        $face = $this->face([
            'OS/2' => FontBuilder::os2(weight: 400, width: 5),
            'fvar' => FontBuilder::fvar([['wght', 100.4, 400, 899.6, 256], ['wdth', 62.5, 100, 112.5, 257], ['opsz', 8, 14, 144, 258]], axisSize: 24),
        ]);

        self::assertTrue($face->isVariable());
        self::assertEquals(new Axis('wght', 100.399993896484375, 400.0, 899.600006103515625, 256), $face->axis('wght'));
        self::assertSame('opsz', $face->axes[2]->tag);
        self::assertSame(258, $face->axes[2]->nameId);
        self::assertNull($face->axis('slnt'));
        self::assertSame([100, 900], $face->weightRange());
        self::assertSame([62.5, 112.5], $face->stretchRange());
        self::assertSame([101, 899], new Font(axes: [new Axis('wght', 100.6, 400, 899.4, 256)])->weightRange());
        self::assertFalse($this->face([])->isVariable());
        self::assertSame([], $this->face([])->axes);
    }

    public function testReadsTheUnicodeRanges(): void
    {
        $face = $this->face(['cmap' => FontBuilder::cmap([[3, 1, FontBuilder::format4([[0x20, 0x7E]])]])]);

        self::assertSame([[0x20, 0x7E]], $face->unicodeRanges);
        self::assertSame([], $this->face([])->unicodeRanges);
    }

    public function testEveryWidthClassHasItsPercentage(): void
    {
        $widths = array_map(static fn (int $class): float => new Font(widthClass: $class)->stretchRange()[0], range(1, 9));

        self::assertSame([50.0, 62.5, 75.0, 87.5, 100.0, 112.5, 125.0, 150.0, 200.0], $widths);
        $default = new Font();
        self::assertSame([400, 400], $default->weightRange());
        self::assertSame(5, $default->widthClass);
        self::assertSame(0.0, $default->italicAngle);
        self::assertFalse($default->fixedPitch);
    }
}
