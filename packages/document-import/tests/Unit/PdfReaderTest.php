<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Pdf\PdfReader;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Tests\Unit\Fixture\RawPdf;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A PDF read page by page: its page tree and media boxes, the streams of each page, its links and annotations, the
 * running headers and footers left out, and its metadata.
 */
#[CoversClass(PdfReader::class)]
final class PdfReaderTest extends TestCase
{
    private const string FONT = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    public function testAnEmptyFormIsNoForm(): void
    {
        self::assertSame(0, $this->import([$this->text('Body', 400)], catalog: ' /AcroForm << >>')->report->count(Omitted::Annotation));
        self::assertSame(1, $this->import([$this->text('Body', 400)], catalog: ' /AcroForm << /Fields [] >>')->report->count(Omitted::Annotation));
    }

    /**
     * @return iterable<string, array{string|null, float, bool}>
     */
    public static function margins(): iterable
    {
        // Without media box, the page goes from 0 to 792: its margins end 95.04 points from the bottom and the top.
        yield 'top margin of a letter page' => [null, 696.96, true];
        yield 'below the top margin' => [null, 696.9, false];
        yield 'bottom margin of a letter page' => [null, 95.0, true];
        yield 'above the bottom margin' => [null, 95.1, false];
        // A box of its own: from 20 to 820, margins of 96.
        yield 'top margin of its own box' => ['[10 20 600 820]', 724.0, true];
        yield 'below the top of its own box' => ['[10 20 600 820]', 723.9, false];
        yield 'bottom margin of its own box' => ['[10 20 600 820]', 116.0, true];
        // A box whose second number is no number counts it 0: from 0 to 800, margins of 96.
        yield 'a box of a name' => ['[0 /x 612 800]', 96.0, true];
        yield 'above the bottom of a box of a name' => ['[0 /x 612 800]', 96.1, false];
        // A box of a reference: the number it leads to.
        yield 'a box of references' => ['[0 9 0 R 612 800]', 96.0, true];
        // A flat box, or a box upside down, is no box: the letter page again.
        yield 'a flat box' => ['[0 0 612 0]', 696.96, true];
        yield 'a box upside down' => ['[0 792 612 0]', 696.96, true];
        yield 'a box of three numbers' => ['[0 0 612]', 696.96, true];
        // The bottom is the second number, the top the fourth: from 500 to 600, margins of 12.
        yield 'a box wider than tall' => ['[0 500 900 600]', 590.0, true];
        yield 'a box narrower than its bottom' => ['[0 500 400 600]', 590.0, true];
        yield 'a box from its right' => ['[700 500 0 600]', 590.0, true];
        // A flat box is no box: the line in the middle of a letter page is kept.
        yield 'a flat box at the line' => ['[0 400 612 400]', 400.0, false];
    }

    #[DataProvider('margins')]
    public function testARunningLineIsInTheMarginsOfItsPage(?string $box, float $y, bool $left): void
    {
        $pages = [];

        for ($page = 1; $page <= 3; ++$page) {
            $pages[] = ['content' => $this->line('Running '.$page, $y).' '.$this->line('Body of page '.$page, 400), 'box' => $box];
        }

        $texts = $this->texts($this->import($pages, objects: [9 => '0']));

        self::assertSame(!$left, str_contains(implode(' ', $texts), 'Running'), implode(' | ', $texts));
    }

    public function testAPageInheritsTheBoxOfItsParentUnlessItHasOne(): void
    {
        $pdf = new RawPdf();
        $pdf->object(3, self::FONT);

        foreach ([1, 2, 3] as $page) {
            $pdf->stream(10 + $page, '', $this->line('Header '.$page, 724).' '.$this->line('Body '.$page, 400));
        }

        // The parent's box goes from 20 to 820; the third page's own box from 0 to 1000.
        $pdf->object(2, '<< /Type /Pages /MediaBox [0 20 600 820] /Resources << /Font << /F1 3 0 R >> >> /Kids [21 0 R 22 0 R 23 0 R] >>');
        $pdf->object(21, '<< /Type /Page /Contents 11 0 R >>');
        $pdf->object(22, '<< /Type /Page /Contents 12 0 R >>');
        $pdf->object(23, '<< /Type /Page /Contents 13 0 R /MediaBox [0 0 600 1000] >>');
        $pdf->object(1, '<< /Type /Catalog /Pages 2 0 R >>');

        $texts = $this->texts(new DocumentImporter()->import($pdf->bytes."trailer << /Root 1 0 R >>\n", Format::Pdf));

        self::assertSame(['Body 1 Body 2 Header 3 Body 3'], [implode(' ', $texts)]);
    }

    public function testAThresholdOfHalfThePagesAndTwoAtLeast(): void
    {
        // On two pages of two: left out.
        self::assertSame(['Body 1', 'Body 2'], $this->texts($this->import($this->running(2, [1, 2]))));
        // On two pages of five, under the three of half: kept; on three of six: left out.
        self::assertCount(7, $this->texts($this->import($this->running(5, [1, 2]))));
        self::assertCount(6, $this->texts($this->import($this->running(6, [1, 2, 3]))));
        // On two pages of six: kept.
        self::assertCount(8, $this->texts($this->import($this->running(6, [1, 2]))));
    }

    public function testDigitsAndSpacesAroundDoNotTellRunningLinesApart(): void
    {
        $pages = [
            ['content' => $this->line(' Page 1', 760).' '.$this->line('Body one', 400)],
            ['content' => $this->line('Page 2 ', 760).' '.$this->line('Body two', 400)],
        ];
        $imported = $this->import($pages);

        self::assertSame(['Body one', 'Body two'], $this->texts($imported));
        self::assertSame(['what' => 'header-footer', 'detail' => 'repeated line', 'count' => 1, 'description' => Omitted::HeaderFooter->description()], $imported->report->toArray()[0]);
    }

    public function testAnImageInTheMarginOfEveryPageIsKept(): void
    {
        $image = static fn (int $page): string => '<< /Type /XObject /Subtype /Image /Width 10 /Height 10 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length 5 >>'."\nstream\n\xFF\xD8\xFF\xE0".$page."\nendstream";
        $pages = [];

        for ($page = 1; $page <= 3; ++$page) {
            $pages[] = ['content' => 'q 50 0 0 50 72 740 cm /Im Do Q '.$this->line('Body '.$page, 400), 'resources' => ' /XObject << /Im '.(8 + $page).' 0 R >>'];
        }

        // Three images in the same place, one a page.
        $blocks = $this->import($pages, objects: [9 => $image(1), 10 => $image(2), 11 => $image(3)])->document->blocks;

        self::assertSame(['image', 'paragraph', 'image', 'paragraph', 'image', 'paragraph'], array_map(static fn (Block $block): string => $block->type, $blocks));
    }

    public function testThePageTreeIsWalkedWithoutTypesAndNoDeeperThanTheLimit(): void
    {
        // A node without type but with kids holds pages; a leaf without type is a page.
        $pdf = new RawPdf();
        $pdf->object(3, self::FONT);
        $pdf->stream(11, '', $this->line('Deep page', 400));
        $pdf->object(20, '<< /Contents 11 0 R >>');

        $previous = 20;

        for ($node = 21; $node <= 24; ++$node) {
            $pdf->object($node, '<< /Kids ['.$previous.' 0 R] >>');
            $previous = $node;
        }

        $pdf->object(2, '<< /Type /Pages /Resources << /Font << /F1 3 0 R >> >> /Kids ['.$previous.' 0 R] >>');
        $pdf->object(1, '<< /Type /Catalog /Pages 2 0 R >>');

        $bytes = $pdf->bytes."trailer << /Root 1 0 R >>\n";

        // The page is five levels below the root: a depth of five reaches it.
        self::assertSame(['Deep page'], $this->texts(new DocumentImporter(limits: new Limits(depth: 5))->import($bytes, Format::Pdf)));

        $this->expectExceptionObject(LimitExceeded::because('The page tree of the PDF is deeper than 4.'));
        new DocumentImporter(limits: new Limits(depth: 4))->import($bytes, Format::Pdf);
    }

    public function testThePageContentIsTheStreamsOfItsList(): void
    {
        $pdf = new RawPdf();
        $pdf->object(3, self::FONT);
        $pdf->stream(11, '', 'BT /F1 10 Tf 72 400 Td (One) Tj ET');
        $pdf->stream(12, '', 'BT /F1 10 Tf 72 388 Td (Two) Tj ET');
        $pdf->object(20, '<< /Type /Page /Contents [11 0 R 12 0 R 13 0 R] >>');
        $pdf->object(2, '<< /Type /Pages /Resources << /Font << /F1 3 0 R >> >> /Kids [20 0 R] >>');
        $pdf->object(1, '<< /Type /Catalog /Pages 2 0 R >>');

        self::assertSame(['One Two'], $this->texts(new DocumentImporter()->import($pdf->bytes."trailer << /Root 1 0 R >>\n", Format::Pdf)));
    }

    public function testNoMoreThanTenThousandAnnotationsAreRead(): void
    {
        $annotations = '['.str_repeat('<< /Subtype /Text >> ', 10_001).']';
        $imported = $this->import([['content' => $this->line('Body', 400), 'extra' => ' /Annots '.$annotations]]);

        self::assertSame(10_000, $imported->report->count(Omitted::Comment));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function links(): iterable
    {
        yield 'web' => ['/A << /S /URI /URI (https://example.org/a) >>', 'https://example.org/a'];
        yield 'mail in capitals' => ['/A << /S /URI /URI (MAILTO:a@example.org) >>', 'MAILTO:a@example.org'];
        yield 'with a space' => ['/A << /S /URI /URI (https://example.org/a b) >>', null];
        yield 'with a line feed after' => ["/A << /S /URI /URI (https://example.org/a\n) >>", null];
        yield 'after other text' => ['/A << /S /URI /URI (see https://example.org) >>', null];
        yield 'script' => ['/A << /S /URI /URI (javascript:alert\\(1\\)) >>', null];
        yield 'no address' => ['/A << /S /URI >>', null];
    }

    #[DataProvider('links')]
    public function testALinkKeepsOnlyAWebOrMailAddress(string $action, ?string $href): void
    {
        $blocks = $this->import([['content' => $this->line('Linked', 400), 'extra' => ' /Annots [<< /Subtype /Link /Rect [60 390 300 420] '.$action.' >>]']])->document->blocks;

        self::assertSame(['text' => null === $href ? 'Linked' : '<a href="'.$href.'">Linked</a>'], $blocks[0]->data);
    }

    public function testInternalLinksAreReported(): void
    {
        $reported = fn (string $annotation): int => $this->import([['content' => $this->line('Text', 400), 'extra' => ' /Annots [<< /Subtype /Link /Rect [60 390 300 420] '.$annotation.' >>]']])->report->count(Omitted::InternalLink);

        self::assertSame([1, 1, 0], [$reported('/A << /S /GoTo /D [1 0 R /Fit] >>'), $reported('/Dest [1 0 R /Fit]'), $reported('/A << /S /Launch /F (x) >>')]);
    }

    public function testALinkCoversTheRunsInsideItsRectangle(): void
    {
        // A run of "Ab" at 5 points from x = 100 to 105, baseline 400: its middle at 102.5, its height at 401.5.
        $link = static fn (string $rect): string => ' /Annots [<< /Subtype /Link /Rect '.$rect.' /A << /S /URI /URI (https://e.org) >> >>]';
        $linked = fn (string $rect): bool => str_contains((string) json_encode($this->import([['content' => 'BT /F1 5 Tf 100 400 Td (Ab) Tj ET', 'extra' => $link($rect)]])->document->blocks[0]->data), 'href');

        self::assertTrue($linked('[102.5 401.5 200 500]'));
        self::assertTrue($linked('[200 500 102.5 401.5]'));
        self::assertTrue($linked('[0 0 102.5 401.5]'));
        self::assertTrue($linked('[102.5 0 0 401.5]'));
        self::assertTrue($linked('[0 410 200 401]'));
        // A link without rectangle covers nothing.
        self::assertSame(['text' => 'Ab'], $this->import([['content' => 'BT /F1 5 Tf 100 400 Td (Ab) Tj ET', 'extra' => ' /Annots [<< /Subtype /Link /A << /S /URI /URI (https://e.org) >> >>]']])->document->blocks[0]->data);
        self::assertFalse($linked('[102.6 0 200 500]'));
        self::assertFalse($linked('[0 0 102.4 500]'));
        self::assertFalse($linked('[0 401.6 200 500]'));
        self::assertFalse($linked('[0 0 200 401.4]'));
    }

    public function testTheLanguageAndDatesOfTheMetadata(): void
    {
        $info = "/CreationDate (D:20260102030405+05'30') /ModDate (xD:20260102) /Title (  )";
        $imported = $this->import([$this->text('Body', 400)], catalog: ' /Lang ( fr-CA )', info: $info);

        self::assertSame(['created' => '2026-01-02T03:04:05+05:30', 'modified' => 'xD:20260102', 'language' => 'fr-CA'], $imported->metadata);
        self::assertSame([], $this->import([$this->text('Body', 400)], catalog: ' /Lang (  )')->metadata);
    }

    /**
     * @param list<int> $on
     *
     * @return list<array{content: string}>
     */
    private function running(int $count, array $on): array
    {
        $pages = [];

        for ($page = 1; $page <= $count; ++$page) {
            $pages[] = ['content' => (\in_array($page, $on, true) ? $this->line('Header', 760).' ' : '').$this->line('Body '.$page, 400)];
        }

        return $pages;
    }

    /**
     * @return array{content: string}
     */
    private function text(string $text, float $y): array
    {
        return ['content' => $this->line($text, $y)];
    }

    private function line(string $text, float $y): string
    {
        return 'BT /F1 10 Tf 72 '.$y.' Td ('.$text.') Tj ET';
    }

    /**
     * @param list<array{content: string, box?: string|null, extra?: string, resources?: string}> $pages
     * @param array<int, string>                                                                  $objects
     */
    private function import(array $pages, string $catalog = '', string $info = '', array $objects = []): Imported
    {
        $pdf = new RawPdf();
        $pdf->object(3, self::FONT);

        foreach ($objects as $number => $object) {
            str_contains($object, 'stream') ? $pdf->append($number." 0 obj\n".$object."\nendobj\n") : $pdf->object($number, $object);
        }

        $kids = [];

        foreach ($pages as $index => $page) {
            $pdf->stream(100 + $index, '', $page['content']);
            $box = null === ($page['box'] ?? null) ? '' : ' /MediaBox '.$page['box'];
            $pdf->object(200 + $index, '<< /Type /Page /Contents '.(100 + $index).' 0 R /Resources << /Font << /F1 3 0 R >>'.($page['resources'] ?? '').' >>'.$box.($page['extra'] ?? '').' >>');
            $kids[] = (200 + $index).' 0 R';
        }

        $pdf->object(2, '<< /Type /Pages /Kids ['.implode(' ', $kids).'] >>');
        $pdf->object(1, '<< /Type /Catalog /Pages 2 0 R'.$catalog.' >>');
        $pdf->object(4, '<< '.$info.' >>');

        return new DocumentImporter()->import($pdf->bytes."trailer << /Root 1 0 R /Info 4 0 R >>\n", Format::Pdf);
    }

    /**
     * @return list<string>
     */
    private function texts(Imported $imported): array
    {
        return array_map(static fn (Block $block): string => \is_string($block->data['text'] ?? null) ? $block->data['text'] : '', $imported->document->blocks);
    }
}
