<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Iterator;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Pdf\PdfBudget;
use Phil\DocumentImport\Internal\Pdf\PdfContent;
use Phil\DocumentImport\Internal\Pdf\PdfDictionary;
use Phil\DocumentImport\Internal\Pdf\PdfDocument;
use Phil\DocumentImport\Internal\Pdf\PdfImages;
use Phil\DocumentImport\Internal\Pdf\PdfRun;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\Tests\Unit\Fixture\Pdf;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where the content of a page puts each string: the text state, the text and graphics matrices, the marked content,
 * the forms and images, run by run — text, start, end, baseline, size, bold, italic, image.
 */
#[CoversClass(PdfContent::class)]
final class PdfContentTest extends TestCase
{
    /**
     * @return Iterator<string, array{string, list<list<bool|float|string>>}>
     */
    public static function contents(): Iterator
    {
        yield 'operators' => ['BT /F1 10 Tf 2 Tc 3 Tw 50 Tz 14 TL 4 Ts 10 20 Td (a b) Tj T* (c) Tj 0 -5 TD (d) Tj (e) \' 1 2 (f) " ET', [['a b', 10.0, 22.0, 24.0, 10.0, false, false, false], ['c', 10.0, 13.5, 10.0, 10.0, false, false, false], ['d', 10.0, 13.5, 5.0, 10.0, false, false, false], ['e', 10.0, 13.5, 0.0, 10.0, false, false, false], ['f', 10.0, 13.5, -5.0, 10.0, false, false, false]]];
        yield 'kerning' => ['BT /F1 12 Tf 1 0 0 1 100 500 Tm [(A) -300 (B) 120 (C) -200 (D) -201 (E) /x (F)] TJ ET', [['A', 100.0, 106.0, 500.0, 12.0, false, false, false], [' ', 109.6, 109.6, 500.0, 12.0, false, false, false], ['B', 109.6, 115.6, 500.0, 12.0, false, false, false], ['C', 114.16, 120.16, 500.0, 12.0, false, false, false], ['D', 122.56, 128.56, 500.0, 12.0, false, false, false], [' ', 130.972, 130.972, 500.0, 12.0, false, false, false], ['E', 130.972, 136.972, 500.0, 12.0, false, false, false], ['F', 136.972, 142.972, 500.0, 12.0, false, false, false]]];
        yield 'matrices' => ['2 0 0 2 10 10 cm BT /F1 10 Tf 5 5 Td (x) Tj ET q 1 0 0 1 50 0 cm BT /F1 10 Tf (y) Tj ET Q BT /F1 10 Tf (z) Tj ET Q BT /F1 10 Tf (w) Tj ET', [['x', 20.0, 30.0, 20.0, 20.0, false, false, false], ['y', 110.0, 120.0, 10.0, 20.0, false, false, false], ['z', 10.0, 20.0, 10.0, 20.0, false, false, false], ['w', 10.0, 20.0, 10.0, 20.0, false, false, false]]];
        yield 'rotated' => ['0 1 -1 0 300 100 cm BT /F1 10 Tf (r) Tj ET', [['r', 300.0, 300.0, 100.0, 10.0, false, false, false]]];
        yield 'flat matrix' => ['BT /F1 12 Tf 0 0 0 0 10 10 Tm (s) Tj ET', [['s', 10.0, 10.0, 10.0, 12.0, false, false, false]]];
        yield 'negative size' => ['BT /F1 -12 Tf 10 10 Td (n) Tj ET', [['n', 10.0, 4.0, 10.0, 12.0, false, false, false]]];
        yield 'fonts' => ['BT /B 10 Tf (bold) Tj /I 10 Tf (italic) Tj /Nope 10 Tf (lost) Tj /F1 (x) Tf (kept) Tj ET', [['bold', 0.0, 20.0, 0.0, 10.0, true, false, false], ['italic', 20.0, 50.0, 0.0, 10.0, false, true, false], ['kept', 50.0, 70.0, 0.0, 10.0, false, false, false]]];
        yield 'no font' => ['BT 10 10 Td (none) Tj ET', []];
        yield 'damaged operators' => ['1 0 0 1 10 cm 1 0 0 1 /x 20 cm BT /F1 10 Tf 1 0 0 1 5 Tm 7 Td (p) Tj 3 TD (q) Tj ET', [['p', 7.0, 12.0, 20.0, 10.0, false, false, false], ['q', 10.0, 15.0, 20.0, 10.0, false, false, false]]];
        yield 'begin text' => ['BT /F1 10 Tf 30 40 Td ET BT (b) Tj ET', [['b', 0.0, 5.0, 0.0, 10.0, false, false, false]]];
        yield 'artifacts' => ['/Artifact BMC BT /F1 10 Tf (hidden) Tj /P BDC (hidden too) Tj EMC (still hidden) Tj EMC (shown) Tj EMC EMC (after) Tj ET /Span <</MCID 0>> BDC BT /F1 10 Tf (span) Tj ET EMC', [['shown', 140.0, 165.0, 0.0, 10.0, false, false, false], ['after', 165.0, 190.0, 0.0, 10.0, false, false, false], ['span', 0.0, 20.0, 0.0, 10.0, false, false, false]]];
        yield 'saved states' => [str_repeat('q ', 256).'1 0 0 1 100 0 cm q 1 0 0 1 50 0 cm Q BT /F1 10 Tf (t) Tj ET', [['t', 0.0, 5.0, 0.0, 10.0, false, false, false]]];
        yield 'empty string' => ['BT /F1 10 Tf () Tj [()] TJ ET', []];
        yield 'generic matrices' => ['1 0.5 0.25 2 10 20 cm 0.5 0.2 0 1.5 3 4 cm BT /F1 10 Tf 2 0.3 0.4 1.5 30 40 Tm 0.5 Ts 80 Tz (g h) Tj [(i) -300 (j)] TJ ET', [['g h', 45.891, 60.441, 171.38, 48.239, false, false, false], ['i', 60.441, 65.291, 197.78, 48.239, false, false, false], [' ', 68.201, 68.201, 211.86, 48.239, false, false, false], ['j', 68.201, 73.051, 211.86, 48.239, false, false, false]]];
        yield 'turned the other way' => ['0 -1 1 0 100 300 cm BT /F1 10 Tf (t) Tj ET', [['t', 100.0, 100.0, 300.0, 10.0, false, false, false]]];
        yield 'defaults' => ['BT /F1 10 Tf 2 Tc Tc 2 Tw Tw 5 TL TL 3 Ts Ts 10 10 Td (a b) Tj T* (c) Tj Td (d) Tj TD (e) Tj 2 Tw 3 Tc " (f g) Tj ET', [['a b', 10.0, 25.0, 10.0, 10.0, false, false, false], ['c', 10.0, 15.0, 10.0, 10.0, false, false, false], ['d', 10.0, 15.0, 10.0, 10.0, false, false, false], ['e', 10.0, 15.0, 10.0, 10.0, false, false, false], ['f g', 10.0, 25.0, 10.0, 10.0, false, false, false]]];
        yield 'spaced line' => ['BT /F1 10 Tf 10 Td 4 (a b) " (c d) Tj ET', [['c d', 10.0, 29.0, 0.0, 10.0, false, false, false]]];
        yield 'kerning scaled' => ['BT /F1 10 Tf 50 Tz 2 0 0 2 0 0 Tm [(A) -400 (B)] TJ (C) Tj ET', [['A', 0.0, 5.0, 0.0, 20.0, false, false, false], [' ', 9.0, 9.0, 0.0, 20.0, false, false, false], ['B', 9.0, 14.0, 0.0, 20.0, false, false, false], ['C', 14.0, 19.0, 0.0, 20.0, false, false, false]]];
        yield 'inline font' => ['BT /F9 10 Tf (inline) Tj /F1 10 Tf (one) Tj /F9 10 Tf (again) Tj ET', [['inline', 0.0, 30.0, 0.0, 10.0, true, false, false], ['one', 30.0, 45.0, 0.0, 10.0, false, false, false], ['again', 45.0, 70.0, 0.0, 10.0, true, false, false]]];
        yield 'unbalanced end' => ['EMC BT /F1 10 Tf /Artifact BMC (a) Tj EMC (b) Tj ET', [['b', 5.0, 10.0, 0.0, 10.0, false, false, false]]];
        yield 'nested marks' => ['BT /F1 10 Tf /P BMC /Artifact BMC (a) Tj EMC (b) Tj EMC (c) Tj EMC EMC (d) Tj ET', [['b', 5.0, 10.0, 0.0, 10.0, false, false, false], ['c', 10.0, 15.0, 0.0, 10.0, false, false, false], ['d', 15.0, 20.0, 0.0, 10.0, false, false, false]]];
        yield 'marks inside an artifact' => ['BT /F1 10 Tf /Artifact BMC /P BMC EMC (a) Tj EMC (b) Tj ET', [['b', 5.0, 10.0, 0.0, 10.0, false, false, false]]];
        yield 'artifact after a closed mark' => ['BT /F1 10 Tf /P BMC EMC /Artifact BMC (a) Tj EMC (b) Tj ET', [['b', 5.0, 10.0, 0.0, 10.0, false, false, false]]];
        yield 'artifact with properties' => ['BT /F1 10 Tf /Artifact <</Type /Pagination>> BDC (a) Tj EMC (b) Tj ET', [['b', 5.0, 10.0, 0.0, 10.0, false, false, false]]];
        yield 'string operands' => ['1 0 0 1 (7) 5 cm BT /F1 10 Tf (s) Tj ET', [['s', 0.0, 5.0, 5.0, 10.0, false, false, false]]];
    }

    /**
     * @param list<list<bool|float|string>> $runs
     */
    #[DataProvider('contents')]
    public function testEachStringIsPlacedOnThePage(string $content, array $runs): void
    {
        self::assertSame($runs, $this->runs($content, ' /Font << /F1 3 0 R /B 4 0 R /I 5 0 R /F9 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> >>'));
    }

    public function testAnImageIsNeverReadAsContent(): void
    {
        $pdf = new Pdf();
        $data = "\xFF\xD8\xFF\xE0 BT /F1 10 Tf (z) Tj ET";
        $image = $pdf->add('<< /Type /XObject /Subtype /Image /Width 10 /Height 10 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.\strlen($data)." >>\nstream\n".$data."\nendstream");

        self::assertSame([['', 0.0, 1.0, 1.0, 1.0, false, false, true]], $this->runs('/Im Do', ' /XObject << /Im '.$image.' 0 R >>', $pdf));
    }

    public function testTheMatrixOfAFormReadsOnlyItsNumbers(): void
    {
        $pdf = new Pdf();
        $form = $pdf->add("<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Matrix [1 0 0 1 (7) 2.5] /Length 23 >>\nstream\nBT /F1 10 Tf (f) Tj ET\nendstream");

        self::assertSame([['f', 0.0, 5.0, 2.5, 10.0, false, false, false]], $this->runs('/Fm Do', ' /XObject << /Fm '.$form.' 0 R >>', $pdf));
    }

    public function testImagesArePlacedByTheGraphicsMatrix(): void
    {
        $pdf = new Pdf();
        $image = $pdf->add("<< /Type /XObject /Subtype /Image /Width 10 /Height 10 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length 4 >>\nstream\n\xFF\xD8\xFF\xE0\nendstream");
        $resources = ' /XObject << /Im '.$image.' 0 R >>';

        self::assertSame([['', 20.0, 120.0, -20.0, 50.0, false, false, true]], $this->runs('q 100 0 0 -50 20 30 cm /Im Do Q', $resources, $pdf));
        self::assertSame([], $this->runs('/Artifact BMC q 100 0 0 50 20 30 cm /Im Do Q EMC (x) Do /Missing Do', $resources, $pdf));
    }

    public function testFormsAreEnteredWithTheirMatrixAndResources(): void
    {
        $pdf = new Pdf();
        $inner = $pdf->add("<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Length 23 >>\nstream\nBT /F1 10 Tf (i) Tj ET\nendstream");
        $form = $pdf->add('<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Matrix [1 0 0 1 10 0] /Resources << /Font << /F1 4 0 R >> /XObject << /In '.$inner." 0 R >> >> /Length 34 >>\nstream\nBT /F1 10 Tf (f) Tj ET /In Do\nendstream");
        $runs = $this->runs('1 0 0 1 0 100 cm BT 1 0 0 1 300 300 Tm /Fm Do (after) Tj ET', ' /XObject << /Fm '.$form.' 0 R >>', $pdf);

        // The form's own resources give it the bold font; the form inside it has none of its own and keeps them.
        self::assertSame([
            ['f', 10.0, 15.0, 100.0, 10.0, true, false, false],
            ['i', 10.0, 15.0, 100.0, 10.0, true, false, false],
            ['after', 300.0, 325.0, 400.0, 10.0, true, false, false],
        ], $runs);
    }

    public function testFormsNestNoDeeperThanThirtyTwoNorThanTheLimit(): void
    {
        $pdf = new Pdf();
        $next = null;

        for ($level = 34; $level >= 1; --$level) {
            $content = 'BT /F1 10 Tf ('.$level.') Tj ET'.(null === $next ? '' : ' /X Do');
            $resources = null === $next ? '' : ' /Resources << /Font << /F1 3 0 R >> /XObject << /X '.$next.' 0 R >> >>';
            $next = $pdf->add('<< /Type /XObject /Subtype /Form /BBox [0 0 1 1]'.$resources.' /Length '.\strlen($content)." >>\nstream\n".$content."\nendstream");
        }

        $texts = static fn (array $runs): array => array_map(static fn (array $run): string => $run[0], $runs);

        self::assertSame(array_map(strval(...), range(1, 32)), $texts($this->runs('/X Do', ' /XObject << /X '.$next.' 0 R >>', clone $pdf)));
        self::assertSame(['1', '2'], $texts($this->runs('/X Do', ' /XObject << /X '.$next.' 0 R >>', $pdf, 2)));
    }

    public function testEachPageStartsAfresh(): void
    {
        [$content, $resources] = $this->content(new Pdf());
        $first = $content->page('2 0 0 2 50 50 cm /Artifact BMC BT /F1 10 Tf (a) Tj ET', $resources);
        $second = $content->page('BT /F1 10 Tf (b) Tj ET', $resources);

        self::assertSame([], $first);
        self::assertSame([['b', 0.0, 5.0, 0.0, 10.0, false, false, false]], array_map($this->row(...), $second));
    }

    /**
     * @return list<array{string, float, float, float, float, bool, bool, bool}>
     */
    private function runs(string $content, string $resources = '', ?Pdf $pdf = null, int $depth = 100): array
    {
        [$reader, $dictionary] = $this->content($pdf ?? new Pdf(), $resources, $depth);

        return array_map($this->row(...), $reader->page($content, $dictionary));
    }

    /**
     * @return array{PdfContent, PdfDictionary}
     */
    private function content(Pdf $pdf, string $resources = '', int $depth = 100): array
    {
        $document = new PdfDocument($pdf->page('', resources: $resources)->bytes(), new Limits());
        $out = new Builder(new MediaFolder(), new SvgSanitizer());
        $kids = $document->resolve($document->dictionary($document->dictionary($document->trailer->get('Root'))->get('Pages'))->get('Kids'));
        self::assertIsArray($kids);
        $page = $document->dictionary($kids[\count($kids) - 1]);

        return [new PdfContent($document, new PdfImages($document, $out), $out, $depth, new PdfBudget(1_000_000)), $document->dictionary($page->get('Resources'))];
    }

    /**
     * @return array{string, float, float, float, float, bool, bool, bool}
     */
    private function row(PdfRun $run): array
    {
        return [$run->text, round($run->x, 3), round($run->end, 3), round($run->y, 3), round($run->size, 3), $run->bold, $run->italic, $run->image instanceof Media];
    }
}
