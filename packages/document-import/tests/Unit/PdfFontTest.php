<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Internal\Pdf\GlyphNames;
use Phil\DocumentImport\Internal\Pdf\PdfCMap;
use Phil\DocumentImport\Internal\Pdf\PdfDocument;
use Phil\DocumentImport\Internal\Pdf\PdfFont;
use Phil\DocumentImport\Internal\Pdf\PdfReference;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Tests\Unit\Fixture\Pdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a font says of its strings: the codes they split into, the text and width of each, and whether the font is
 * bold or italic.
 */
#[CoversClass(PdfFont::class)]
#[CoversClass(PdfCMap::class)]
#[CoversClass(GlyphNames::class)]
final class PdfFontTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function styles(): iterable
    {
        yield 'plain' => ['/BaseFont /Times-Roman', false, false];
        yield 'subset named black' => ['/BaseFont /ABCDEF+Arial-Black', true, false];
        yield 'oblique' => ['/BaseFont /Helvetica-Oblique', false, true];
        yield 'flag of force bold' => ['/BaseFont /X /FontDescriptor << /Flags 262144 >>', true, false];
        yield 'flags below force bold' => ['/BaseFont /X /FontDescriptor << /Flags 262143 >>', false, true];
        yield 'flag of italic' => ['/BaseFont /X /FontDescriptor << /Flags 64 >>', false, true];
        yield 'flags beside italic' => ['/BaseFont /X /FontDescriptor << /Flags 33 >>', false, false];
        yield 'flags of a float' => ['/BaseFont /X /FontDescriptor << /Flags 262208.0 >>', false, false];
        yield 'weight 600' => ['/BaseFont /X /FontDescriptor << /FontWeight 600 >>', true, false];
        yield 'weight 599' => ['/BaseFont /X /FontDescriptor << /FontWeight 599 >>', false, false];
        yield 'weight of a name' => ['/BaseFont /X /FontDescriptor << /FontWeight /Bold >>', false, false];
        yield 'descendant of a composite font' => ['/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /FontDescriptor << /Flags 262208 >> >> << /FontDescriptor << /Flags 0 >> >>] /FontDescriptor << /Flags 0 >>', true, true];
        yield 'composite font without descendant' => ['/Subtype /Type0 /BaseFont /X /DescendantFonts 5 /FontDescriptor << /Flags 262208 >>', false, false];
        yield 'simple font of its own descriptor' => ['/BaseFont /X /DescendantFonts [<< /FontDescriptor << /Flags 0 >> >>] /FontDescriptor << /Flags 262208 >>', true, true];
    }

    #[DataProvider('styles')]
    public function testTheStyleComesFromTheNameAndTheDescriptor(string $entries, bool $bold, bool $italic): void
    {
        $font = $this->font($entries);

        self::assertSame([$bold, $italic], [$font->bold, $font->italic]);
    }

    public function testASimpleFontReadsItsWidthsFromItsFirstCharacter(): void
    {
        $font = $this->font('/BaseFont /X /FirstChar 65 /Widths [600 700.5 /x 8 0 R]', [8 => '900']);

        self::assertSame([['A', 600.0, false], ['B', 700.5, false], ['C', 500.0, false], ['D', 900.0, false], ['E', 500.0, false], [' ', 500.0, true], ['@', 500.0, false]], $font->glyphs('ABCDE @'));
        self::assertSame([], $this->font('/BaseFont /X /FirstChar 65.0 /Widths [600]')->glyphs(''));
        self::assertSame([['A', 500.0, false]], $this->font('/BaseFont /X /FirstChar 65.0 /Widths [600]')->glyphs('A'));
        self::assertSame([['A', 500.0, false]], $this->font('/BaseFont /X /FirstChar 65 /Widths 600')->glyphs('A'));
    }

    public function testASimpleFontHoldsTheWidthsOfItsTwoHundredAndFiftySixCodes(): void
    {
        $widths = implode(' ', array_map(static fn (int $code): string => (string) (1000 + $code), range(0, 256)));
        $glyphs = $this->font('/BaseFont /X /FirstChar 0 /Widths ['.$widths.']')->glyphs("\x00\xFF");

        self::assertSame([1000.0, 1255.0], [$glyphs[0][1], $glyphs[1][1]]);
    }

    public function testTheDifferencesRenameCodes(): void
    {
        $font = $this->font('/BaseFont /X /Encoding << /Differences [/A 65 /Aring /unknown /B 97 /uni00E9] >>');

        self::assertSame(['A', 'Å', 'B', 'B', 'é', 'b', '’'], array_map(static fn (array $glyph): string => $glyph[0], $font->glyphs("\x00ABCab'")));
    }

    public function testTheDifferencesStopAtFiveHundredAndTwelveItems(): void
    {
        $font = $this->font('/BaseFont /X /Encoding << /Differences ['.str_repeat('1 ', 510).'65 /B /C] >>');

        self::assertSame(['B', 'B'], array_map(static fn (array $glyph): string => $glyph[0], $font->glyphs('AB')));
    }

    public function testTheBaseEncodingOfTheDifferences(): void
    {
        self::assertSame('é', $this->font('/BaseFont /X /Encoding << /BaseEncoding /MacRomanEncoding >>')->glyphs("\x8E")[0][0]);
        self::assertSame('‘', $this->font('/BaseFont /X /Encoding << >>')->glyphs('`')[0][0]);
        self::assertSame('é', $this->font('/BaseFont /X /Encoding /MacRomanEncoding')->glyphs("\x8E")[0][0]);
        self::assertSame('`', $this->font('/BaseFont /X')->glyphs('`')[0][0]);
        self::assertSame('', $this->font('/BaseFont /X')->glyphs("\x1F")[0][0]);
    }

    public function testACompositeFontReadsCodesOfTwoBytes(): void
    {
        $font = $this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /DW 800 /W [/x 65 [600 700] 70 72 400 /x 90 (y) 300 80 75 200] >>]');

        self::assertSame([['A', 600.0, false], ['B', 700.0, false], ['C', 800.0, false], ['F', 400.0, false], ['H', 400.0, false], ['I', 800.0, false], ['', 800.0, false], ['P', 800.0, false], ['Z', 800.0, false]], $font->glyphs("\x00A\x00B\x00C\x00F\x00H\x00I\x00\x00\x00P\x00Z"));
        self::assertSame([['A', 1000.0, false]], $this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /DW /x >>]')->glyphs("\x00A"));
    }

    public function testACompositeFontStopsReadingWidthsPastItsLimit(): void
    {
        $font = $this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /W [0 65535 1 0 0 3 70000 [2] 1 1 4] >>]');
        $widths = static fn (PdfFont $font, string $codes): array => array_map(static fn (array $glyph): float => $glyph[1], $font->glyphs($codes));

        // Entries are read while no more than 65 536 widths are held: the code 0 given again is read, the code 1
        // after a width of code 70 000 is not.
        self::assertSame([3.0, 1.0, 1.0], $widths($font, "\x00\x00\x00\x01\xFF\xFF"));
        self::assertSame([5.0, 5.0], $widths($this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /W [0 ['.str_repeat('5 ', 65_537).']] >>]'), "\x00\x00\xFF\xFF"));
        // No more than 65 536 entries of the list are read: the width of code 100 is the last one.
        self::assertSame([9.0], $widths($this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /W ['.str_repeat('0 [5] ', 32_767).'100 [9] 100 [8]] >>]'), "\x00d"));
        self::assertSame([7.0, 7.0], $widths($this->font('/Subtype /Type0 /BaseFont /X /DescendantFonts [<< /W [0 70000 7] >>]'), "\x00\x00\xFF\xFF"));
    }

    public function testTheCMapSaysHowLongEachCodeIs(): void
    {
        $cmap = "begincodespacerange\n<00> <7F>\n<8000> <FFFF>\nendcodespacerange\nbeginbfchar\n<41> <0058>\n<8001> <0059>\nendbfchar";
        $font = $this->font('/Subtype /Type0 /BaseFont /X /ToUnicode 8 0 R /DescendantFonts [<< /W [65 [300] 32769 [400]] >>]', [8 => '<< /Length '.\strlen($cmap)." >>\nstream\n".$cmap."\nendstream"]);

        self::assertSame([['X', 300.0, false], ['Y', 400.0, false], ['B', 1000.0, false]], $font->glyphs("A\x80\x01B"));
    }

    public function testASimpleFontWithACMapKeepsCodesOfOneByte(): void
    {
        $cmap = "begincodespacerange\n<0000> <FFFF>\nendcodespacerange\nbeginbfchar\n<4142> <005A>\nendbfchar";
        $font = $this->font('/BaseFont /X /ToUnicode 8 0 R', [8 => '<< /Length '.\strlen($cmap)." >>\nstream\n".$cmap."\nendstream"]);

        self::assertSame(['Z', 'C'], array_map(static fn (array $glyph): string => $glyph[0], $font->glyphs('ABC')));
    }

    /**
     * @param array<int, string> $objects
     */
    private function font(string $entries, array $objects = []): PdfFont
    {
        $pdf = new Pdf();

        foreach ($objects as $number => $object) {
            while ($pdf->add('null') < $number - 1) {
                // Objects before the one a test numbers.
            }

            $pdf->add($object);
        }

        $number = $pdf->add('<< /Type /Font '.$entries.' >>');
        $document = new PdfDocument($pdf->page('')->bytes(), new Limits());

        return new PdfFont($document->dictionary(new PdfReference($number)), $document);
    }
}
