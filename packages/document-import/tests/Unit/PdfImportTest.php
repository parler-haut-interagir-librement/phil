<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Pdf\PdfContent;
use Phil\DocumentImport\Internal\Pdf\PdfDocument;
use Phil\DocumentImport\Internal\Pdf\PdfFont;
use Phil\DocumentImport\Internal\Pdf\PdfImages;
use Phil\DocumentImport\Internal\Pdf\PdfLayout;
use Phil\DocumentImport\Internal\Pdf\PdfLine;
use Phil\DocumentImport\Internal\Pdf\PdfReader;
use Phil\DocumentImport\Internal\Pdf\PdfRun;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Tests\Unit\Fixture\Pdf;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImporter::class)]
#[CoversClass(Builder::class)]
#[CoversClass(Inline::class)]
#[CoversClass(PdfReader::class)]
#[CoversClass(PdfDocument::class)]
#[CoversClass(PdfContent::class)]
#[CoversClass(PdfFont::class)]
#[CoversClass(PdfImages::class)]
#[CoversClass(PdfLayout::class)]
#[CoversClass(PdfLine::class)]
#[CoversClass(PdfRun::class)]
final class PdfImportTest extends TestCase
{
    private const string TEXT = "BT /F1 24 Tf 72 720 Td (Title) Tj ET\n"
        ."BT /F1 12 Tf 72 690 Td (First line of the) Tj 0 -14 Td (paragraph goes on.) Tj ET\n"
        ."BT /B 12 Tf 72 640 Td (Bold) Tj /I 12 Tf ( italic) Tj ET\n"
        ."BT /F1 12 Tf 72 600 Td (\\225 Bullet one) Tj 0 -14 Td (\\225 Bullet two) Tj 20 -14 Td (- Nested) Tj ET\n"
        ."BT /F1 12 Tf 72 540 Td (1. First) Tj 0 -14 Td (2. Second) Tj ET\n"
        ."BT /F1 12 Tf 72 490 Td (A word cut by hyph-) Tj 0 -14 Td (enation here.) Tj ET\n"
        ."BT /F1 12 Tf 72 440 Td [(Kern)-300(ed)] TJ ( x) Tj ET\n";

    public function testTheTextBecomesHeadingsParagraphsAndLists(): void
    {
        $imported = $this->import(new Pdf()->page(self::TEXT));
        $item = static fn (string $content, array $items = []): array => ['content' => $content, 'meta' => [], 'items' => $items];

        self::assertSame(Format::Pdf, $imported->format);
        self::assertSame([
            ['header', ['text' => 'Title', 'level' => 1]],
            ['paragraph', ['text' => 'First line of the paragraph goes on.']],
            ['paragraph', ['text' => '<b>Bold</b><i> italic</i>']],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Bullet one'), $item('Bullet two', [$item('Nested')])]]],
            ['list', ['style' => 'ordered', 'meta' => [], 'items' => [$item('First'), $item('Second')]]],
            ['paragraph', ['text' => 'A word cut by hyphenation here.']],
            ['paragraph', ['text' => 'Kern ed x']],
        ], $this->blocks($imported));
        self::assertTrue($imported->report->isEmpty());
    }

    public function testCrossReferenceStreamsObjectStreamsAndScanningGiveTheSameText(): void
    {
        $pdf = new Pdf()->page(self::TEXT, compressed: true);
        $expected = $this->blocks($this->import($pdf));

        self::assertSame($expected, $this->blocks($this->import($pdf, 'stream')));
        self::assertSame($expected, $this->blocks($this->import($pdf, 'none')));
        self::assertCount(7, $expected);
    }

    public function testRunningHeadersAreLeftOutAndParagraphsGoOnAcrossPages(): void
    {
        $header = static fn (int $page): string => 'BT /F1 9 Tf 72 770 Td (The Book - page '.$page.') Tj ET ';
        $imported = $this->import(new Pdf()
            ->page($header(1).'BT /F1 12 Tf 72 700 Td (A sentence that goes) Tj ET BT /F1 12 Tf 72 686 Td (on and) Tj ET')
            ->page($header(2).'BT /F1 12 Tf 72 700 Td (ends here.) Tj ET BT /F1 12 Tf 72 680 Td (Next one.) Tj ET')
            ->page($header(3).'BT /F1 12 Tf 72 700 Td (New page, New paragraph.) Tj ET'));

        self::assertSame([
            ['paragraph', ['text' => 'A sentence that goes on and ends here. Next one.']],
            ['paragraph', ['text' => 'New page, New paragraph.']],
        ], $this->blocks($imported));
        self::assertSame([['header-footer', 'repeated line', 1]], $this->report($imported));
    }

    public function testLinksAnnotationsAndFormsAreReadOrReported(): void
    {
        $imported = $this->import(new Pdf()->catalog(' /AcroForm << /Fields [] >> /Lang (fr-CA)')->page(
            'BT /F1 12 Tf 72 700 Td (Visit) Tj ( the site) Tj ET BT /F1 12 Tf 72 600 Td (Elsewhere) Tj ET',
            ' /Annots [<< /Subtype /Link /Rect [100 690 300 715] /A << /S /URI /URI (https://example.com/a) >> >>'
            .' << /Subtype /Link /Rect [0 0 10 10] /A << /S /URI /URI (javascript:alert) >> >>'
            .' << /Subtype /Link /Rect [0 0 10 10] /A << /S /GoTo /D [1 0 R /Fit] >> >>'
            .' << /Subtype /Link /Rect [0 0 10 10] /Dest (there) >>'
            .' << /Subtype /Link /A << /S /URI /URI (https://example.com/b) >> >>'
            .' << /Subtype /Text /Contents (A note) >> << /Subtype /Popup >> << /Subtype /Highlight >> << /Subtype /Widget >>]',
        ));

        self::assertSame([
            ['paragraph', ['text' => 'Visit<a href="https://example.com/a"> the site</a>']],
            ['paragraph', ['text' => 'Elsewhere']],
        ], $this->blocks($imported));
        self::assertSame([['annotation', 'form', 1], ['annotation', 'widget', 1], ['internal-link', '', 2], ['comment', '', 2]], $this->report($imported));
        self::assertSame(['language' => 'fr-CA'], $imported->metadata);
    }

    public function testTheDocumentInformationIsTheMetadata(): void
    {
        $imported = $this->import(new Pdf()->info('/Title (Rules \\(v2\\)) /Author <FEFF0041006E006E> /Subject (\\351t\\351) /Keywords <EFBBBF4AC3A97520> /CreationDate (D:20261112093015-05\'00\') /ModDate (D:2026) /Producer (x) /Creator 12')->page('BT /F1 12 Tf 72 700 Td (x) Tj ET'));

        self::assertSame([
            'title' => 'Rules (v2)',
            'creator' => 'Ann',
            'subject' => 'été',
            'keywords' => 'Jéu',
            'created' => '2026-11-12T09:30:15-05:00',
            'modified' => '2026-01-01T00:00:00',
        ], $imported->metadata);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dates(): iterable
    {
        yield 'UTC' => ['D:20261112093015Z', '2026-11-12T09:30:15Z'];
        yield 'lower z' => ['D:20261112093015z', '2026-11-12T09:30:15Z'];
        yield 'east, no minutes' => ['D:202611120930+02', '2026-11-12T09:30:00+02:00'];
        yield 'date only' => ['20261112', '2026-11-12T00:00:00'];
        yield 'not a date' => ['yesterday', 'yesterday'];
    }

    #[DataProvider('dates')]
    public function testDatesAreReadAsIso8601(string $date, string $iso): void
    {
        $imported = $this->import(new Pdf()->info('/CreationDate ('.$date.')')->page(''));

        self::assertSame(['created' => $iso], $imported->metadata);
    }

    public function testImagesAreTakenOutOnceAndTheOthersReported(): void
    {
        $pdf = new Pdf();
        $jpeg = $pdf->add("<< /Type /XObject /Subtype /Image /Width 10 /Height 10 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length 4 >>\nstream\n\xFF\xD8\xFF\xE0\nendstream");
        $gray = (string) gzcompress(str_repeat("\x80", 64));
        $png = $pdf->add('<< /Type /XObject /Subtype /Image /Width 8 /Height 8 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length '.\strlen($gray)." >>\nstream\n".$gray."\nendstream");
        $cmyk = $pdf->add("<< /Type /XObject /Subtype /Image /Width 8 /Height 8 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Length 4 >>\nstream\nabcd\nendstream");
        $jpx = $pdf->add("<< /Type /XObject /Subtype /Image /Width 8 /Height 8 /Filter /JPXDecode /Length 4 >>\nstream\nwxyz\nendstream");
        $tiny = $pdf->add("<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /Length 4 >>\nstream\nabcd\nendstream");
        $form = $pdf->add("<< /Type /XObject /Subtype /Form /Matrix [1 0 0 1 0 -100] /Length 34 >>\nstream\nBT /F1 12 Tf 72 400 Td (Form) Tj ET\nendstream");
        $pdf->page(
            "BT /F1 12 Tf 72 700 Td (Before) Tj ET q 100 0 0 50 72 600 cm /J Do Q q 8 0 0 8 72 500 cm /G Do /G Do /C Do /X Do /T Do /Missing Do Q /Fm Do\nBT /F1 12 Tf 72 100 Td (After) Tj ET BI /W 1 /H 1 ID \x00\x01 EI",
            resources: ' /XObject << /J '.$jpeg.' 0 R /G '.$png.' 0 R /C '.$cmyk.' 0 R /X '.$jpx.' 0 R /T '.$tiny.' 0 R /Fm '.$form.' 0 R >>',
        );
        $imported = $this->import($pdf);

        self::assertSame(['paragraph', 'image', 'image', 'paragraph', 'paragraph'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
        self::assertSame(['media/image-1.jpg', 'media/image-2.png'], array_keys($imported->media));
        self::assertSame("\xFF\xD8\xFF\xE0", $imported->media['media/image-1.jpg']->contents);
        self::assertStringStartsWith("\x89PNG\r\n\x1A\n\0\0\0\rIHDR\0\0\0\x08\0\0\0\x08\x08\0", $imported->media['media/image-2.png']->contents);
        self::assertSame('image/png', $imported->media['media/image-2.png']->mediaType);
        self::assertSame('Form', $imported->document->blocks[3]->data['text']);
        self::assertSame([['image', 'pdf colour space', 1], ['image', 'pdf JPXDecode', 1], ['image', 'inline', 1]], $this->report($imported));
    }

    public function testArtifactsAreLeftOut(): void
    {
        $imported = $this->import(new Pdf()->page('/Artifact BMC BT /F1 12 Tf 72 770 Td (12) Tj ET EMC /P << /MCID 0 >> BDC BT /F1 12 Tf 72 700 Td (Text) Tj ET EMC EMC'));

        self::assertSame([['paragraph', ['text' => 'Text']]], $this->blocks($imported));
    }

    public function testACompositeFontIsReadThroughItsCMap(): void
    {
        $pdf = new Pdf();
        $cmap = "/CIDInit /ProcSet findresource begin begincmap 1 begincodespacerange <0000> <FFFF> endcodespacerange\n"
            .'2 beginbfchar <0001> <0048> <0002> <00E9> endbfchar 1 beginbfrange <0003> <0004> <006C> endbfrange endcmap';
        $map = $pdf->add('<< /Length '.\strlen($cmap)." >>\nstream\n".$cmap."\nendstream");
        $descendant = $pdf->add('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ABCDEF+Gentium-Italic /DW 600 /W [1 [500 400] 3 4 300] >>');
        $font = $pdf->add('<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEF+Gentium-Italic /Encoding /Identity-H /DescendantFonts ['.$descendant.' 0 R] /ToUnicode '.$map.' 0 R >>');
        $imported = $this->import($pdf->page('BT /T 12 Tf 72 700 Td <0001000200030004> Tj ET', resources: ' /Font << /T '.$font.' 0 R >>'));

        self::assertSame([['paragraph', ['text' => '<i>Hélm</i>']]], $this->blocks($imported));
    }

    public function testASimpleFontReadsItsDifferences(): void
    {
        $pdf = new Pdf();
        $font = $pdf->add('<< /Type /Font /Subtype /Type1 /BaseFont /Custom /FirstChar 65 /Widths [600 600] /Encoding << /BaseEncoding /WinAnsiEncoding /Differences [65 /eacute /uni00E0 70 /fi] >> >>');
        $mac = $pdf->add('<< /Type /Font /Subtype /Type1 /BaseFont /Mac /Encoding /MacRomanEncoding >>');
        $standard = $pdf->add('<< /Type /Font /Subtype /Type1 /BaseFont /Std /Encoding /StandardEncoding >>');
        $imported = $this->import($pdf->page(
            'BT /D 12 Tf 72 700 Td (ABF\\351) Tj /M 12 Tf ( \\216) Tj /S 12 Tf ( \\047\\140) Tj /F1 12 Tf ( \\001) Tj /Nothing 12 Tf (gone) Tj ET',
            resources: ' /Font << /D '.$font.' 0 R /M '.$mac.' 0 R /S '.$standard.' 0 R >>',
        ));

        self::assertSame([['paragraph', ['text' => 'éàfié é ’‘']]], $this->blocks($imported));
    }

    public function testAnEncryptedPdfIsRefused(): void
    {
        $pdf = new Pdf()->page('BT /F1 12 Tf 72 700 Td (x) Tj ET')->bytes();

        $this->expectExceptionObject(UnsupportedFormat::because('The PDF is encrypted, which this library does not read: save it again without password nor restrictions.'));
        new DocumentImporter()->import(str_replace('/Root 1 0 R', '/Root 1 0 R /Encrypt << /Filter /Standard >>', $pdf), Format::Pdf);
    }

    public function testAFileThatIsNotAPdfIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::because('The file is not a PDF document: it does not start with "%PDF-".'));
        new DocumentImporter()->import('Hello', Format::Pdf);
    }

    public function testAPdfWithoutPagesGivesAnEmptyDocument(): void
    {
        $imported = new DocumentImporter()->import("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF", filename: 'empty.pdf');

        self::assertSame([], $imported->document->blocks);
    }

    public function testTooManyObjectsAreRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The PDF declares more than 3 objects.'));
        new DocumentImporter(limits: new Limits(objects: 3))->import(new Pdf()->page('')->bytes(), Format::Pdf);
    }

    public function testTooManyScannedObjectsAreRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The PDF declares more than 3 objects.'));
        new DocumentImporter(limits: new Limits(objects: 3))->import(new Pdf()->page('')->bytes('none'), Format::Pdf);
    }

    public function testNestingIsBounded(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The PDF nests arrays and dictionaries deeper than 5.'));
        new DocumentImporter(limits: new Limits(depth: 5))->import(new Pdf()->page('[[[[[[[[1]]]]]]]] TJ')->bytes(), Format::Pdf);
    }

    public function testAPdfLargerThanTheLimitIsRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The file is larger than 100 bytes.'));
        new DocumentImporter(limits: new Limits(totalBytes: 100))->import(new Pdf()->page('')->bytes(), Format::Pdf);
    }

    public function testAStreamLargerThanTheLimitOnceDecodedIsRefused(): void
    {
        $content = str_repeat('BT ET ', 400);

        $this->expectExceptionObject(LimitExceeded::because('A stream of the PDF is larger than 1000 bytes once decoded.'));
        new DocumentImporter(limits: new Limits(entryBytes: 1000))->import(new Pdf()->page($content, compressed: true)->bytes(), Format::Pdf);
    }

    public function testAPageTreeThatLoopsEnds(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [2 0 R 3 0 R] >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F 5 0 R >> >> >> endobj\n"
            ."4 0 obj << /Length 31 >>\nstream\nBT /F 12 Tf 72 700 Td (x) Tj ET\nendstream endobj\n5 0 obj << /Type /Font /Subtype /Type1 >> endobj\ntrailer << /Root 1 0 R >>";

        self::assertSame([['paragraph', ['text' => 'x']]], $this->blocks(new DocumentImporter()->import($pdf, Format::Pdf)));
    }

    public function testAPageTreeDeeperThanTheLimitIsRefused(): void
    {
        $objects = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";

        for ($number = 2; $number < 10; ++$number) {
            $objects .= $number.' 0 obj << /Type /Pages /Kids ['.($number + 1)." 0 R] >> endobj\n";
        }

        $this->expectExceptionObject(LimitExceeded::because('The page tree of the PDF is deeper than 4.'));
        new DocumentImporter(limits: new Limits(depth: 4))->import("%PDF-1.4\n".$objects.'trailer << /Root 1 0 R >>', Format::Pdf);
    }

    /**
     * @param 'table'|'stream'|'none' $references
     */
    private function import(Pdf $pdf, string $references = 'table'): Imported
    {
        return new DocumentImporter()->import($pdf->bytes($references), filename: 'book.pdf');
    }

    /**
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function blocks(Imported $imported): array
    {
        return array_map(static function (Block $block): array {
            $array = $block->toArray();

            return [$array['type'], $array['data']];
        }, $imported->document->blocks);
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function report(Imported $imported): array
    {
        return array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $imported->report->toArray());
    }
}
