<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Bytes;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\Number;
use Phil\PagedMedia\Pdf\Objects;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMedia\Tests\Unit\Fixture\PdfReader;
use Phil\PagedMedia\Tests\Unit\Fixture\PngBuilder;
use Phil\PagedMedia\Tests\Unit\Fixture\TrueTypeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Objects::class)]
#[CoversClass(Number::class)]
#[CoversClass(Bytes::class)]
final class DocumentTest extends TestCase
{
    public function testADocumentOfStandardTextIsAValidPdf(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(54, 700, 'Thé (vert) \\ €', 12, StandardFont::HelveticaBold);
        $pdf->textRight(558, 680, '12.50');
        $pdf->addPage(PageSize::a4());
        $pdf->text(54.5, 700.25, "Two\r\nlines");

        $reader = new PdfReader($pdf->render());

        self::assertSame(2, $pdf->pageCount());
        self::assertSame('<< /Type /Catalog /Pages 2 0 R >>', $reader->object(1));
        self::assertSame('<< /Type /Pages /Kids [6 0 R 8 0 R] /Count 2 >>', $reader->object(2));
        self::assertSame('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>', $reader->object(3));
        self::assertSame('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', $reader->object(4));
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents 5 0 R >>', $reader->object(6));
        self::assertStringContainsString('/MediaBox [0 0 595 842]', $reader->object(8));
        self::assertSame("BT /F1 12 Tf 54 700 Td (Th\xE9 \\(vert\\) \\\\ \x80) Tj ET\nBT /F2 10 Tf 532.98 680 Td (12.50) Tj ET\nBT /F2 10 Tf 54.5 700.25 Td (Two lines) Tj ET\n", $reader->contents());
        self::assertSame('<< /Producer (phil/paged-media) >>', $reader->object(9));
    }

    public function testTheSameCallsGiveTheSameBytes(): void
    {
        $write = static function (): string {
            $pdf = new Document();
            $pdf->addPage();
            $pdf->text(10, 10, 'Même');

            return $pdf->render();
        };

        self::assertSame($write(), $write());
    }

    public function testStreamsAreCompressedUnlessAskedNot(): void
    {
        $pdf = new Document();
        $pdf->addPage();
        $pdf->text(10, 20, 'x');

        $reader = new PdfReader($pdf->render());

        self::assertStringStartsWith('<< /Filter /FlateDecode /Length ', $reader->object(4));
        self::assertSame("BT /F1 10 Tf 10 20 Td (x) Tj ET\n", $reader->contents());

        $plain = new Document(compress: false);
        $plain->addPage();
        $plain->text(10, 20, 'x');
        self::assertStringStartsWith('<< /Length 32 >>', new PdfReader($plain->render())->object(4));
    }

    public function testColouredTextLinesAndRectangles(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(1, 2, 'a', 9, color: Color::rgb(255, 0, 51));
        $pdf->line(0, 0.5, 100, 0.5);
        $pdf->line(1, 2, 3, 4, 0.25, Color::gray(51));
        $pdf->rectangle(10, 20, 30, 40);
        $pdf->rectangle(10, 20, 30, 40, Color::gray(255));
        $pdf->rectangle(10, 20, 30, 40, stroke: Color::gray(0), lineWidth: 2);
        $pdf->rectangle(10, 20, 30, 40, Color::hex('#fff'), Color::hex('#000000'), 0.5);

        self::assertSame(implode("\n", [
            'q 1 0 0.2 rg BT /F1 9 Tf 1 2 Td (a) Tj ET Q',
            'q 1 w 0 0.5 m 100 0.5 l S Q',
            'q 0.25 w 0.2 G 1 2 m 3 4 l S Q',
            'q 1 w 10 20 30 40 re S Q',
            'q 1 w 1 g 10 20 30 40 re f Q',
            'q 2 w 0 G 10 20 30 40 re S Q',
            'q 0.5 w 1 1 1 rg 0 0 0 RG 10 20 30 40 re B Q',
        ])."\n", new PdfReader($pdf->render())->contents());
    }

    public function testTheMetadataIsWrittenInTheInformationDictionary(): void
    {
        $pdf = new Document(PageSize::a5(), new Metadata('Thé', 'Phil (G)', 'Reçus', 'pdf, test', 'App', new DateTimeImmutable('2026-11-05 14:30:15', new DateTimeZone('America/Toronto'))));
        $pdf->addPage();

        self::assertSame(
            '<< /Title <FEFF0054006800E9> /Author (Phil \\(G\\)) /Subject <FEFF0052006500E700750073> /Keywords (pdf, test) /Creator (App) /Producer (phil/paged-media) /CreationDate (D:20261105143015-05\'00\') >>',
            new PdfReader($pdf->render())->object(5),
        );
    }

    public function testAnEmbeddedFontIsSubsetWithItsWidthsAndUnicodeMap(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0xE9 => 550])->composite(0x43, 650, [0x41, 0x42])->build());
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(50, 480, 'ACé', 14, $font);
        $pdf->text(50, 460, 'A?', 14, $font);
        $pdf->textRight(200, 440, 'A', 10, $font);

        $reader = new PdfReader($pdf->render());

        self::assertSame("BT /F1 14 Tf 50 480 Td <000100030004> Tj ET\nBT /F1 14 Tf 50 460 Td <00010000> Tj ET\nBT /F1 10 Tf 194 440 Td <0001> Tj ET\n", $reader->contents());
        $type0 = $reader->find('/Subtype /Type0');
        self::assertSame(1, preg_match('/\/BaseFont \/([A-P]{6})\+TestSans-Regular \/Encoding \/Identity-H \/DescendantFonts \[(\d+) 0 R\] \/ToUnicode (\d+) 0 R/', $type0, $type0Match));
        $cid = $reader->object((int) $type0Match[2]);
        self::assertStringContainsString('/Subtype /CIDFontType2 /BaseFont /'.$type0Match[1].'+TestSans-Regular /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>', $cid);
        self::assertStringContainsString('/W [0 [500] 1 [600] 3 [650] 4 [550]] /CIDToGIDMap /Identity', $cid);
        self::assertSame("/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n4 beginbfchar\n<0000> <003F>\n<0001> <0041>\n<0003> <0043>\n<0004> <00E9>\nendbfchar\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n", PdfReader::data($reader->object((int) $type0Match[3])));
        $descriptor = $reader->find('/Type /FontDescriptor');
        self::assertSame(1, preg_match('/\A<< \/Type \/FontDescriptor \/FontName \/[A-P]{6}\+TestSans-Regular \/Flags 32 \/FontBBox \[-100 -200 1100 900\] \/ItalicAngle 0 \/Ascent 800 \/Descent -200 \/CapHeight 700 \/StemV 80 \/FontFile2 (\d+) 0 R >>\z/', $descriptor, $file));
        $subset = PdfReader::data($reader->object((int) $file[1]));
        self::assertStringContainsString('/Length1 '.\strlen($subset).' ', $reader->object((int) $file[1]));
        self::assertSame($font->subset([0, 1, 3, 4]), $subset);
        self::assertStringContainsString('/BaseFont /BJPIAA+TestSans-Regular ', $type0, 'The tag is drawn from the name and the glyphs.');
    }

    public function testTheTagOfASubsetChangesWithTheNameAndTheGlyphs(): void
    {
        $tag = static function (string $name, string $text): string {
            $pdf = new Document(compress: false);
            $pdf->addPage();
            $pdf->text(0, 0, $text, 10, TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 600])->named($name)->build()));
            self::assertSame(1, preg_match('/\/BaseFont \/([A-P]{6})\+/', new PdfReader($pdf->render())->find('/Subtype /Type0'), $match));

            return $match[1];
        };

        self::assertSame('JADMLH', $tag('TestSans-Regular', 'A'));
        self::assertNotSame($tag('TestSans-Regular', 'A'), $tag('TestSans-Bold', 'A'));
        self::assertNotSame($tag('TestSans-Regular', 'A'), $tag('TestSans-Regular', 'B'));
    }

    public function testACharacterMapsTheGlyphItFirstPrints(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, 'A?', 10, TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build()));
        $pdf->text(0, 0, '€', 10, TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build()));

        $reader = new PdfReader($pdf->render());

        self::assertSame(2, substr_count($reader->pdf, '/Subtype /Type0'), 'Two fonts read from the same bytes are two fonts.');
        self::assertStringContainsString("BT /F1 10 Tf 0 0 Td <00010000> Tj ET\nBT /F2 10 Tf 0 0 Td <0000> Tj ET\n", $reader->contents());

        $same = new Document(compress: false);
        $same->addPage();

        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $same->text(0, 0, 'A?', 10, $font);
        $same->text(0, 0, '€', 10, $font);

        self::assertStringContainsString("<0000> <003F>\n<0001> <0041>\n", $this->unicodeMap(new PdfReader($same->render())));
    }

    public function testAnItalicFontOfVariablePitchIsFlaggedItalic(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 10, TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->italic(-12)->build()));

        self::assertStringContainsString(' /Flags 96 ', new PdfReader($pdf->render())->find('/Type /FontDescriptor'));
    }

    public function testAFontFileIsCompressedWithItsLength(): void
    {
        $pdf = new Document();
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 10, TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build()));

        self::assertMatchesRegularExpression('/\A<< \/Length1 \d+ \/Filter \/FlateDecode \/Length \d+ >>/', new PdfReader($pdf->render())->find('/Length1 '));
    }

    public function testAStreamIsCompressedAtTheHighestLevel(): void
    {
        // A text on which the levels 8 and 9 differ: at the second "K", level 9 looks one byte further for a longer match.
        $random = '';

        for ($index = 0; $index < 4; ++$index) {
            $random .= hash('sha256', (string) $index);
        }

        $text = 'K'.substr($random, 0, 180).' '.$random.' K'.$random;
        $pdf = new Document();
        $pdf->addPage();
        $pdf->text(0, 0, $text);

        $content = (string) gzcompress("BT /F1 10 Tf 0 0 Td ({$text}) Tj ET\n", 9);

        self::assertSame('<< /Filter /FlateDecode /Length '.\strlen($content)." >>\nstream\n".$content."\nendstream", new PdfReader($pdf->render())->object(4));
    }

    public function testEachPageTakesTheSizeOfTheDocument(): void
    {
        $pdf = new Document(PageSize::a4(), compress: false);
        $pdf->addPage();

        self::assertStringContainsString('/MediaBox [0 0 595 842]', new PdfReader($pdf->render())->find('/Type /Page '));
    }

    public function testCodePointsBeyondTheBasicPlaneAreMappedInUtf16(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x1F600 => 1000])->format12()->build());
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, '😀', 10, $font);

        self::assertStringContainsString("<0001> <D83DDE00>\n", $this->unicodeMap(new PdfReader($pdf->render())));
    }

    public function testAUnicodeMapIsWrittenInBlocksOfAHundred(): void
    {
        $characters = [];

        for ($codePoint = 0x100; $codePoint < 0x100 + 150; ++$codePoint) {
            $characters[$codePoint] = 500;
        }

        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with($characters)->build());
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, implode('', array_map(mb_chr(...), array_keys($characters))), 10, $font);

        $cmap = $this->unicodeMap(new PdfReader($pdf->render()));

        self::assertSame(1, substr_count($cmap, "100 beginbfchar\n"));
        self::assertSame(1, substr_count($cmap, "50 beginbfchar\n"));
        self::assertSame(2, substr_count($cmap, "endbfchar\n"));
    }

    public function testAFontThatForbidsSubsettingIsEmbeddedWholeWithoutATag(): void
    {
        $bytes = TrueTypeBuilder::with([0x41 => 600])->embedding(0x0100)->italic(-12.5)->fixedPitch()->build();
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 10, TrueTypeFont::fromBytes($bytes));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/FontName /TestSans-Regular /Flags 97 ', $reader->find('/Type /FontDescriptor'));
        self::assertStringContainsString('/ItalicAngle -12.5 ', $reader->find('/Type /FontDescriptor'));
        self::assertStringContainsString('/BaseFont /TestSans-Regular /Encoding', $reader->find('/Subtype /Type0'));
        self::assertSame($bytes, PdfReader::data($reader->find('/Length1 ')));
    }

    public function testFontsAndImagesAreWrittenOnceHoweverOftenTheyAreUsed(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $image = Image::fromBytes(PngBuilder::build(1, 1, 0, 8, ["\x80"]));
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 10, $font);
        $pdf->text(0, 0, 'A', 10, StandardFont::Helvetica);
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 12, $font);
        $pdf->text(0, 0, 'B', 12, StandardFont::Helvetica);
        $pdf->image($image, 1, 2, 3, 4);
        $pdf->image($image, 5, 6, 7, 8);

        $reader = new PdfReader($pdf->render());

        self::assertSame(1, substr_count($reader->pdf, '/Subtype /Type0'));
        self::assertSame(1, substr_count($reader->pdf, '/BaseFont /Helvetica '));
        self::assertSame(1, substr_count($reader->pdf, '/Subtype /Image'));
        self::assertStringContainsString("q 3 0 0 4 1 2 cm /Im1 Do Q\nq 7 0 0 8 5 6 cm /Im1 Do Q\n", $reader->contents());
        self::assertMatchesRegularExpression('/\/Resources << \/Font << \/F1 \d+ 0 R \/F2 \d+ 0 R >> \/XObject << \/Im1 \d+ 0 R >> >>/', $reader->pdf);
    }

    public function testImagesAreWrittenWithTheirColourSpaceFilterAndMask(): void
    {
        $gray = Image::fromBytes(PngBuilder::build(2, 1, 0, 8, ["\x00\xFF"]));
        $alpha = Image::fromBytes(PngBuilder::build(1, 1, 6, 8, ["\xFF\x00\x00\x80"]));
        $jpeg = Image::fromBytes(ImageTest::jpeg(4, true));
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->image($gray, 0, 0, 2, 1);
        $pdf->image($alpha, 0, 0, 1, 1);
        $pdf->image($jpeg, 0, 0, 1, 1);

        $reader = new PdfReader($pdf->render());

        self::assertStringStartsWith('<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns 2 >> /Length ', $reader->find('/Width 2 '));
        self::assertSame(1, preg_match('/<< \/Type \/XObject \/Subtype \/Image \/Width 1 \/Height 1 \/ColorSpace \/DeviceRGB \/BitsPerComponent 8 \/Filter \/FlateDecode \/SMask (\d+) 0 R \/Length \d+ >>/', $reader->pdf, $mask));
        self::assertStringStartsWith('<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ', $reader->object((int) $mask[1]));
        self::assertSame("\x80", gzuncompress(PdfReader::data($reader->object((int) $mask[1]))));
        self::assertStringStartsWith('<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /DCTDecode /Decode [1 0 1 0 1 0 1 0] /Length ', $reader->find('/DCTDecode'));
        self::assertSame(ImageTest::jpeg(4, true), PdfReader::data($reader->find('/DCTDecode')));
    }

    public function testATextIsWrappedBetweenItsWords(): void
    {
        $width = static fn (string $text): float => StandardFont::Helvetica->width($text, 10);

        self::assertSame(['Dice of'], Document::wrap('Dice of', 100, 10));
        self::assertSame([''], Document::wrap('', 100, 10));
        self::assertSame(['A set of', 'polyhedral dice', 'in a bag'], Document::wrap("A set of polyhedral\ndice in a bag", 75, 10));
        self::assertSame(['AAAAA', 'AAAAA', 'A b'], Document::wrap('AAAAAAAAAAA b', 34, 10));
        self::assertSame(['AAAAA', 'b'], Document::wrap('AAAAA b', $width('AAAAA'), 10));
        self::assertSame(['Dice of'], Document::wrap(" Dice of \n", $width('Dice of'), 10), 'Surrounding spaces take no room.');
        self::assertSame(['AA AA', 'AA'], Document::wrap('AA AA AA', $width('AA AA'), 10), 'Words that fill the line exactly stay on it.');
        self::assertSame(['AB', 'AB', 'C'], Document::wrap('ABABC', $width('AB'), 10), 'A word cut where it overflows keeps its letters in order, and fills each line exactly.');
        self::assertSame(['éé', 'èê', 'à'], Document::wrap('ééèêà', $width('éé'), 10), 'A word is cut between characters, never inside one.');
        self::assertSame(['A', 'Æ', 'A'], Document::wrap('AÆA', $width('A?'), 10), 'Nor inside a character wider than the half of it that would fit.');
        self::assertSame(['AAA', 'AAA'], Document::wrap('AAA AAA', StandardFont::HelveticaBold->width('AAA', 10), 10, StandardFont::HelveticaBold), 'A text is measured in its own font.');
    }

    public function testNothingIsDrawnBeforeAPage(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('Add a page before drawing on it.');

        new Document()->line(0, 0, 1, 1);
    }

    public function testAFontSizeIsAboveZero(): void
    {
        $pdf = new Document();
        $pdf->addPage();

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A font size is above zero; 0 is not.');

        $pdf->text(0, 0, 'x', 0);
    }

    private function unicodeMap(PdfReader $reader): string
    {
        self::assertSame(1, preg_match('/\/ToUnicode (\d+) 0 R/', $reader->find('/Subtype /Type0'), $match));

        return PdfReader::data($reader->object((int) $match[1]));
    }

    public function testNumbersAreWrittenWithoutTrailingZerosOrNegativeZero(): void
    {
        self::assertSame('12', Number::format(12.0));
        self::assertSame('12.5', Number::format(12.5));
        self::assertSame('0.33', Number::format(1 / 3));
        self::assertSame('0.333', Number::format(1 / 3, 3));
        self::assertSame('0', Number::format(-0.001));
        self::assertSame('-1.25', Number::format(-1.25));
        self::assertSame('', Bytes::hex(''));
        self::assertSame('00FF10', Bytes::hex("\x00\xFF\x10"));
    }
}
