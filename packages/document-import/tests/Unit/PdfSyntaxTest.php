<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Pdf\GlyphNames;
use Phil\DocumentImport\Internal\Pdf\PdfCMap;
use Phil\DocumentImport\Internal\Pdf\PdfDictionary;
use Phil\DocumentImport\Internal\Pdf\PdfDocument;
use Phil\DocumentImport\Internal\Pdf\PdfImages;
use Phil\DocumentImport\Internal\Pdf\PdfKeyword;
use Phil\DocumentImport\Internal\Pdf\PdfName;
use Phil\DocumentImport\Internal\Pdf\PdfParser;
use Phil\DocumentImport\Internal\Pdf\PdfReference;
use Phil\DocumentImport\Internal\Pdf\PdfStream;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Tests\Unit\Fixture\RawPdf;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pieces of PDF syntax at their edges: values and their nesting, CMaps, glyph names, and the images written
 * again as PNG files, read back chunk by chunk.
 */
#[CoversClass(PdfParser::class)]
#[CoversClass(PdfCMap::class)]
#[CoversClass(GlyphNames::class)]
#[CoversClass(PdfImages::class)]
final class PdfSyntaxTest extends TestCase
{
    public function testNestingIsBoundedByTheDepth(): void
    {
        self::assertSame([[[1]]], new PdfParser('[[[1]]]', 0, 3)->value());
        self::assertEquals([new PdfDictionary(['A' => new PdfDictionary(['B' => 1])])], new PdfParser('[<< /A << /B 1 >> >>]', 0, 3)->value());

        foreach (['[[[[1]]]]', '[[<< /A [1] >>]]', '[[<< /A 1 >>]]'] as $deep) {
            try {
                new PdfParser($deep, 0, 3)->value();
                self::assertSame('[[<< /A 1 >>]]', $deep, $deep);
            } catch (LimitExceeded $limitExceeded) {
                self::assertSame('The PDF nests arrays and dictionaries deeper than 3.', $limitExceeded->getMessage());
            }
        }

        $this->expectException(LimitExceeded::class);
        new PdfParser(str_repeat('[', 102).str_repeat(']', 102))->value();
    }

    public function testTheKeyOfADictionaryCountsInTheDepth(): void
    {
        self::assertInstanceOf(PdfDictionary::class, new PdfParser('<< [1] 1 >>', 0, 2)->value());

        $this->expectException(LimitExceeded::class);

        new PdfParser('<< [[1]] 1 >>', 0, 2)->value();
    }

    public function testReferencesOfADictionaryAreFollowedByTheNextEntries(): void
    {
        self::assertEquals(
            new PdfDictionary(['A' => new PdfReference(1, 2), 'B' => 3, 'C' => new PdfReference(4, 0)]),
            new PdfParser('<< /A 1 2 R /B 3 /C 4 0 R >>')->value(),
        );
    }

    public function testAReferenceOfAnArrayTakesExactlyItsThreeItems(): void
    {
        self::assertEquals([new PdfReference(1, 0)], new PdfParser('[1 0 R]')->value());
        self::assertEquals([7, new PdfReference(1, 2), 3], new PdfParser('[7 1 2 R 3]')->value());
        self::assertEquals([5, new PdfKeyword('R')], new PdfParser('[5 R]')->value());
        self::assertEquals([1, new PdfName('N'), new PdfKeyword('R')], new PdfParser('[1 /N R]')->value());
        self::assertEquals([new PdfName('N'), 2, new PdfKeyword('R')], new PdfParser('[/N 2 R]')->value());
    }

    public function testAnUnterminatedDictionaryEndsTwoBytesPastTheData(): void
    {
        $parser = new PdfParser('<< /A 1');
        $parser->value();

        self::assertSame(9, $parser->at);

        $parser = new PdfParser('<< /A 1 >>x');
        $parser->value();

        self::assertSame(10, $parser->at);
    }

    public function testADictionaryEndsAtItsClosingMarkOnly(): void
    {
        self::assertEquals(new PdfDictionary(['A' => 1]), new PdfParser('<< /A 1 >>>')->value());
    }

    public function testTheOperatorAfterAnInlineImageIsRead(): void
    {
        $operations = iterator_to_array(new PdfParser("BI /W 1 ID abc\nEI Q")->operations(), false);

        self::assertSame(['BI', 'ID', 'EI', 'Q'], array_column($operations, 0));
    }

    public function testARangeListCannotHoldMoreThanTheEntriesALimitAllows(): void
    {
        $ranges = '';

        for ($code = 0; $code <= 65_536; ++$code) {
            $ranges .= \sprintf('<%06X> <%06X> <0041> ', $code, $code);
        }

        // A CMap of this size is more than the default backtrack limit of PCRE reads.
        $limit = ini_set('pcre.backtrack_limit', '100000000');

        try {
            $cmap = new PdfCMap('1 beginbfrange '.$ranges.' endbfrange');
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        self::assertSame('A', $cmap->text("\x00\xFF\xFF"));
        self::assertNull($cmap->text("\x01\x00\x00"));
    }

    public function testTheTextsOfARangeCannotHoldMoreThanTheEntriesALimitAllows(): void
    {
        $cmap = new PdfCMap('1 beginbfrange <00000000> <00010000> ['.str_repeat('<0041>', 65_537).'] endbfrange');

        self::assertSame('A', $cmap->text("\x00\x00\xFF\xFF"));
        self::assertNull($cmap->text("\x00\x01\x00\x00"));
    }

    public function testTheDefaultDepthIsAHundred(): void
    {
        self::assertIsArray(new PdfParser(str_repeat('[', 101).str_repeat(']', 101))->value());
    }

    public function testValuesFollowOneAnother(): void
    {
        $parser = new PdfParser('[<41><42>] [1 ] 5 <</A 1>>6 [null] [1 0 R] [0 R] <</B 1 5 R /C 2>> /A#FF#41');

        self::assertSame(['A', 'B'], $parser->value());
        self::assertSame([1], $parser->value());
        self::assertSame(5, $parser->value());
        self::assertEquals(new PdfDictionary(['A' => 1]), $parser->value());
        self::assertSame(6, $parser->value());
        self::assertSame([null], $parser->value());
        self::assertEquals([new PdfReference(1)], $parser->value());
        self::assertEquals([0, new PdfKeyword('R')], $parser->value());
        self::assertEquals(new PdfDictionary(['B' => new PdfReference(1, 5), 'C' => 2]), $parser->value());
        self::assertEquals(new PdfName("A\xFFA"), $parser->value());
    }

    public function testADictionaryEndsAtItsTwoClosingBrackets(): void
    {
        // A lone ">" is no end: the dictionary reads on, its broken entries left out.
        self::assertEquals([new PdfDictionary(['A' => 1])], new PdfParser('[<</A 1 > /B 2>>7]')->value());
        self::assertEquals([new PdfDictionary(['Type' => new PdfName('Page')]), 5], new PdfParser('[<</Type /Page>> 5]')->value());
        self::assertEquals(new PdfDictionary(['A' => 1]), new PdfParser('<</A 1')->value());
    }

    public function testOperationsLeaveTrailingOperandsAndSkipInlineImages(): void
    {
        $operations = iterator_to_array(new PdfParser('1 2 m BI /W 1 ID xx EI 3 l null 4')->operations(), false);

        self::assertEquals([['m', [1, 2]], ['BI', []], ['ID', [new PdfName('W'), 1]], ['EI', []], ['l', [3]]], $operations);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function glyphNames(): iterable
    {
        yield 'a letter' => ['A', 'A'];
        yield 'two letters' => ['xA', null];
        yield 'a letter and a line feed' => ["A\n", null];
        yield 'uni' => ['uni00E9', 'é'];
        yield 'uni of two' => ['uni00410042', 'AB'];
        yield 'uni after other text' => ['xuni0041', null];
        yield 'uni before other text' => ['uni0041x', null];
        yield 'uni and a line feed' => ["uni0041\n", null];
        yield 'u' => ['u00E9', 'é'];
        yield 'u after other text' => ['xu0041', null];
        yield 'u before other text' => ['u0041x', null];
        yield 'u and a line feed' => ["u0041\n", null];
        yield 'a suffix' => ['A.sc', 'A'];
    }

    #[DataProvider('glyphNames')]
    public function testGlyphNames(string $name, ?string $text): void
    {
        self::assertSame($text, GlyphNames::text($name));
    }

    public function testACMapReadsItsCodeSpaceShortestFirst(): void
    {
        $cmap = new PdfCMap("begincodespacerange\n<0000> <FFFF>\n<00> <7F>\n<0> <9>\nendcodespacerange");

        self::assertSame([1, 2], $cmap->lengths());
        self::assertTrue($cmap->isCode("\x00"));
        self::assertTrue($cmap->isCode("\x7F"));
        self::assertFalse($cmap->isCode("\x80"));
        self::assertTrue($cmap->isCode("\xFF\xFF"));
        self::assertFalse($cmap->isCode("\x00\x00\x00"));
        self::assertSame([], new PdfCMap('')->lengths());
    }

    public function testACMapMapsCharactersAndRangesAtTheirEdges(): void
    {
        $cmap = new PdfCMap(
            "beginbfchar\n<0A1B> <0058>\n<01> <0000>\n<02> <041>\nendbfchar\n"
            ."beginbfrange\n<41> <41> <0059>\n<42> <43> <00E9>\n<45> <44> <005A>\n<50> <52> [<0061> <0062>]\n<1> <3> <0030>\nendbfrange",
        );

        self::assertSame('X', $cmap->text("\x0A\x1B"));
        self::assertSame('', $cmap->text("\x01"));
        self::assertSame('А', $cmap->text("\x02"));
        self::assertSame('Y', $cmap->text('A'));
        self::assertSame('é', $cmap->text('B'));
        self::assertSame('ê', $cmap->text('C'));
        self::assertNull($cmap->text('D'));
        self::assertNull($cmap->text('E'));
        self::assertSame('b', $cmap->text('Q'));
        self::assertNull($cmap->text('R'));
        self::assertSame('2', $cmap->text("\x03"));
    }

    public function testACMapHoldsNoMoreThanItsEntries(): void
    {
        $chars = '';

        for ($code = 0; $code < 65_536; ++$code) {
            $chars .= \sprintf("<%04x> <0041>\n", $code);
        }

        // The pair past the limit would map code 0041 again.
        $cmap = new PdfCMap("beginbfchar\n".$chars."<0041> <0042>\nendbfchar");
        self::assertSame('A', $cmap->text("\x00\x41"));

        $ranges = str_repeat("<FFFF> <FFFF> <0041>\n", 65_536);
        self::assertNull(new PdfCMap("beginbfrange\n".$ranges."<0041> <0041> <0042>\nendbfrange")->text("\x00\x41"));

        $targets = str_repeat('<0041> ', 65_535).'<0042>';
        self::assertSame('B', new PdfCMap("beginbfrange\n<0000> <FFFF> [".$targets."]\nendbfrange")->text("\xFF\xFF"));
    }

    public function testGrayAndRgbSamplesBecomePngFiles(): void
    {
        $gray = implode('', array_map(\chr(...), range(0, 63)));
        $media = $this->image('/ColorSpace /DeviceGray /BitsPerComponent 8 /Width 8 /Height 8', $gray);

        self::assertInstanceOf(Media::class, $media);
        self::assertSame('image-1.png', $media->path);
        self::assertSame([8, 8, 8, 0], $this->header($media->contents));
        self::assertSame($this->rows($gray, 8), $this->pixels($media->contents));

        $rgb = str_repeat("\x10\x20\x30", 64);
        $media = $this->image('/ColorSpace /RGB /BitsPerComponent 8 /Width 8 /Height 8', $rgb);
        self::assertInstanceOf(Media::class, $media);
        self::assertSame([8, 8, 8, 2], $this->header($media->contents));
        self::assertSame($this->rows($rgb, 24), $this->pixels($media->contents));

        self::assertInstanceOf(Media::class, $this->image('/ColorSpace /G /BitsPerComponent 8 /Width 8 /Height 8', $gray));
        self::assertInstanceOf(Media::class, $this->image('/ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 8 /Height 8', $rgb));
    }

    public function testImagesTheReaderCannotWriteAreReported(): void
    {
        $out = new Builder(new MediaFolder(), new SvgSanitizer());
        $gray = str_repeat("\x01", 64);

        self::assertNull($this->image('/ColorSpace /DeviceCMYK /BitsPerComponent 8 /Width 8 /Height 8', $gray, $out));
        self::assertNull($this->image('/ColorSpace /DeviceGray /BitsPerComponent 1 /Width 8 /Height 8', $gray, $out));
        self::assertNull($this->image('/ColorSpace /DeviceGray /BitsPerComponent 8 /Width 8 /Height 8', substr($gray, 1), $out));
        self::assertNull($this->image('/ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 8 /Height 8', str_repeat("\x01", 191), $out));
        self::assertSame(4, $out->report->count(Omitted::Image));
        self::assertSame(['pdf colour space'], array_map(static fn (array $row): string => $row['detail'], $out->report->toArray()));
    }

    public function testSmallImagesAreLeftOutUnreported(): void
    {
        $out = new Builder(new MediaFolder(), new SvgSanitizer());

        foreach (['/Width 7 /Height 8', '/Width 8 /Height 7', '/Width 8.0 /Height 8', '/Width 8 /Height (8)'] as $size) {
            self::assertNull($this->image('/ColorSpace /DeviceGray /BitsPerComponent 8 '.$size, str_repeat("\x01", 64), $out));
        }

        self::assertTrue($out->report->isEmpty());
        self::assertSame('image-1.jpg', $this->image('/Width 8 /Height 8 /Filter /DCTDecode', "\xFF\xD8\xFF\xE0")?->path);
    }

    /**
     * @return array{int, int, int, int} width, height, bit depth and colour type
     */
    private function header(string $png): array
    {
        $chunks = $this->chunks($png);
        self::assertSame(['IHDR', 'IDAT', 'IEND'], array_keys($chunks));
        $header = unpack('Nwidth/Nheight/Cdepth/Ctype/Ccompression/Cfilter/Cinterlace', $chunks['IHDR']);
        self::assertIsArray($header);
        self::assertSame([0, 0, 0], [$header['compression'], $header['filter'], $header['interlace']]);
        [$width, $height, $depth, $type] = [$header['width'], $header['height'], $header['depth'], $header['type']];
        self::assertIsInt($width);
        self::assertIsInt($height);
        self::assertIsInt($depth);
        self::assertIsInt($type);

        return [$width, $height, $depth, $type];
    }

    private function pixels(string $png): string
    {
        return (string) gzuncompress($this->chunks($png)['IDAT']);
    }

    /**
     * @param int<1, max> $row
     */
    private function rows(string $samples, int $row): string
    {
        return implode('', array_map(static fn (string $line): string => "\0".$line, str_split($samples, $row)));
    }

    /**
     * The chunks of a PNG file by their type, each checked against its length and checksum.
     *
     * @return array<string, string>
     */
    private function chunks(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1A\n", $png);
        $chunks = [];

        for ($at = 8; $at < \strlen($png);) {
            $unpacked = unpack('N', substr($png, $at, 4));
            self::assertIsArray($unpacked);
            $length = $unpacked[1];
            self::assertIsInt($length);
            $type = substr($png, $at + 4, 4);
            $data = substr($png, $at + 8, $length);
            $crc = unpack('N', substr($png, $at + 8 + $length, 4));
            self::assertIsArray($crc);
            self::assertSame(crc32($type.$data), $crc[1], $type);
            $chunks[$type] = $data;
            $at += 12 + $length;
        }

        return $chunks;
    }

    private function image(string $dictionary, string $samples, ?Builder $out = null): ?Media
    {
        $pdf = new RawPdf();
        $pdf->object(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $pdf->object(2, '<< /Type /Pages /Kids [] >>');
        $pdf->stream(3, '/Type /XObject /Subtype /Image '.$dictionary, $samples);

        $document = new PdfDocument($pdf->bytes, new Limits());
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        return new PdfImages($document, $out ?? new Builder(new MediaFolder(), new SvgSanitizer()))->image($stream);
    }
}
