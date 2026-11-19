<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Pdf\PdfDictionary;
use Phil\DocumentImport\Internal\Pdf\PdfDocument;
use Phil\DocumentImport\Internal\Pdf\PdfName;
use Phil\DocumentImport\Internal\Pdf\PdfParser;
use Phil\DocumentImport\Internal\Pdf\PdfReference;
use Phil\DocumentImport\Internal\Pdf\PdfStream;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Tests\Unit\Fixture\RawPdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The objects of a PDF found through its cross references rather than by scanning: each file holds, after the object
 * its cross references point to, a decoy of the same number that a scan would keep.
 */
#[CoversClass(PdfDocument::class)]
#[CoversClass(PdfParser::class)]
final class PdfDocumentTest extends TestCase
{
    private const string CATALOG = '<< /Type /Catalog /Pages 2 0 R >>';

    private const string PAGES = '<< /Type /Pages /Kids [] /Count 0 >>';

    public function testATablePointsToItsObjectsRatherThanToTheLastDefinition(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $table = $pdf->table([0 => [null, $offsets[1], $offsets[2], $offsets[3]]], '<< /Size 4 /Root 1 0 R >>');
        $document = new PdfDocument($pdf->startxref($table), new Limits());

        self::assertSame('real', $document->object(3));
        self::assertSame('real', $document->resolve(new PdfReference(3)));
        self::assertNull($document->object(0));
        self::assertNull($document->object(999));
    }

    public function testATableOfSeveralSubsectionsAndATrailerWithoutSpace(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $table = $pdf->append("xref\n0 1\n0000000000 65535 f \n2 2\n".\sprintf("%010d 00000 n \n%010d 00000 n \n", $offsets[2], $offsets[3])."1 1\n".\sprintf("%010d 00000 n \n", $offsets[1]).'trailer<< /Size 4 /Root 1 0 R >>'."\n");
        $document = new PdfDocument($pdf->bytes.'startxref'.$table."\n%%EOF", new Limits());

        self::assertSame('real', $document->object(3));
        self::assertSame('Catalog', $document->dictionary(new PdfReference(1))->name('Type'));
    }

    public function testAnInlinePageTreeNeedsNoScan(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, '<< /Type /Catalog /Pages << /Type /Pages /Kids [] >> >>');
        $real = $pdf->object(3, '(real)');
        $pdf->object(3, '(decoy)');
        $table = $pdf->table([0 => [null, $catalog, null, $real]], '<< /Size 4 /Root 1 0 R >>');

        self::assertSame('real', new PdfDocument($pdf->startxref($table), new Limits())->object(3));
    }

    public function testNewerSectionsWinAndOlderOnesFillIn(): void
    {
        $pdf = new RawPdf();
        $old = [1 => $pdf->object(1, self::CATALOG), 2 => $pdf->object(2, self::PAGES), 3 => $pdf->object(3, '(old)'), 4 => $pdf->object(4, '(only old)'), 5 => $pdf->object(5, '(freed)')];
        $first = $pdf->table([0 => [null, $old[1], $old[2], $old[3], $old[4], $old[5]]], '<< /Size 6 /Root 1 0 R /Info 4 0 R >>');
        $new = $pdf->object(3, '(new)');
        $pdf->object(4, '(decoy)');
        $second = $pdf->table([3 => [$new], 5 => [null]], '<< /Size 6 /Prev '.$first.' >>');
        $document = new PdfDocument($pdf->startxref($second), new Limits());

        self::assertSame('new', $document->object(3));
        self::assertSame('only old', $document->object(4));
        self::assertNull($document->object(5));
        self::assertEquals(new PdfReference(1), $document->trailer->get('Root'));
        self::assertEquals(new PdfReference(4), $document->trailer->get('Info'));
        self::assertSame(6, $document->trailer->get('Size'));
    }

    public function testAChainOfThirtyTwoSectionsIsFollowedAndNoFurther(): void
    {
        $pdf = new RawPdf();
        $eleven = $pdf->object(11, '(eleven)');
        $previous = $pdf->table([11 => [$eleven]], '<< /Size 12 >>');
        $real = $pdf->object(10, '(real)');
        $previous = $pdf->table([10 => [$real]], '<< /Prev '.$previous.' >>');

        for ($section = 3; $section <= 32; ++$section) {
            $previous = $pdf->table([], '<< /Prev '.$previous.' >>');
        }

        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $pdf->object(10, '(decoy)');
        $newest = $pdf->table([1 => [$catalog, $pages]], '<< /Root 1 0 R /Prev '.$previous.' >>');

        // The thirty-two newest sections are read: the oldest of them gives object 10; the thirty-third is not read.
        $document = new PdfDocument($pdf->startxref($newest), new Limits());
        self::assertSame('real', $document->object(10));
        self::assertNull($document->object(11));
    }

    public function testASectionThatPointsBackToItselfEnds(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $at = \strlen($pdf->bytes);
        $table = $pdf->table([0 => [null, $offsets[1], $offsets[2], $offsets[3]]], '<< /Size 4 /Root 1 0 R /Prev '.$at.' >>');

        self::assertSame('real', new PdfDocument($pdf->startxref($table), new Limits())->object(3));
    }

    public function testAnOffsetPastTheEndIsNoSection(): void
    {
        [$pdf] = $this->decoyed();

        self::assertSame('decoy', new PdfDocument($pdf->startxref(1_000_000), new Limits())->object(3));
    }

    public function testATrailerThatIsNoDictionaryLeavesTheFileToBeScanned(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $table = $pdf->table([0 => [null, $offsets[1], $offsets[2], $offsets[3]]], '5');

        self::assertSame('decoy', new PdfDocument($pdf->startxref($table), new Limits())->object(3));
    }

    public function testATableWithoutTrailerLeavesTheFileToBeScanned(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $table = $pdf->append("xref\n0 4\n0000000000 65535 f \n".\sprintf("%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n", $offsets[1], $offsets[2], $offsets[3]));

        self::assertSame('decoy', new PdfDocument($pdf->startxref($table), new Limits())->object(3));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function crossReferenceStreams(): iterable
    {
        yield 'declared widths' => ['/W [1 2 1] /Size 4', 'n'];
        yield 'default widths' => ['/Size 4', 'n'];
        yield 'type left to its default' => ['/W [0 2 1] /Size 4', 'type'];
        yield 'wide offsets' => ['/W [1 8 0] /Size 4', 'wide'];
        yield 'two subsections' => ['/W [1 2 1] /Index [0 2 2 2]', 'n'];
        yield 'too wide is the default' => ['/W [1 9 1] /Size 4', 'n'];
        yield 'negative is the default' => ['/W [1 -2 1] /Size 4', 'n'];
        yield 'not whole is the default' => ['/W [1 2.0 1] /Size 4', 'n'];
        yield 'two widths are the default' => ['/W [1 2] /Size 4', 'n'];
        yield 'wide type' => ['/W [2 3 1] /Size 4', 'wide type'];
        yield 'wide generation' => ['/W [1 2 2] /Size 4', 'wide generation'];
        yield 'a damaged subsection skipped' => ['/W [1 2 1] /Index [(a) 9 0 4]', 'n'];
        yield 'an odd index' => ['/W [1 2 1] /Index [0 4 9]', 'n'];
    }

    #[DataProvider('crossReferenceStreams')]
    public function testACrossReferenceStreamPointsToItsObjects(string $dictionary, string $rows): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $row = match ($rows) {
            'n' => static fn (?int $offset): string => null === $offset ? "\x00\x00\x00\x00" : "\x01".pack('n', $offset)."\x00",
            'type' => static fn (?int $offset): string => null === $offset ? "\x00\x00\x00" : pack('n', $offset)."\x00",
            'wide type' => static fn (?int $offset): string => null === $offset ? str_repeat("\x00", 6) : "\x00\x01\x00".pack('n', $offset)."\x00",
            'wide generation' => static fn (?int $offset): string => null === $offset ? str_repeat("\x00", 5) : "\x01".pack('n', $offset)."\x00\x00",
            default => static fn (?int $offset): string => null === $offset ? "\x00".str_repeat("\x00", 8) : "\x01".pack('J', $offset),
        };
        $data = $row(null).$row($offsets[1]).$row($offsets[2]).$row($offsets[3]);

        if ('type' === $rows) {
            // A row whose type is left to its default is an object in use: the free first row is written as one.
            $data = pack('n', 0)."\x00".substr($data, 3);
        }

        $stream = $pdf->xrefStream(9, $dictionary.' /Root 1 0 R', $data);
        $document = new PdfDocument($pdf->startxref($stream), new Limits());

        self::assertSame('real', $document->object(3));
    }

    public function testRowsBeyondTheDeclaredCountAreIgnored(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $extra = $pdf->object(4, '(extra)');
        $row = static fn (int $offset): string => "\x01".pack('n', $offset)."\x00";
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Size 4 /Root 1 0 R', "\x00\x00\x00\x00".$row($offsets[1]).$row($offsets[2]).$row($offsets[3]).$row($extra));
        $document = new PdfDocument($pdf->startxref($stream), new Limits());

        self::assertSame('real', $document->object(3));
        self::assertNull($document->object(4));
    }

    public function testTruncatedRowsEndTheSection(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $row = static fn (int $offset): string => "\x01".pack('n', $offset)."\x00";
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Size 6 /Root 1 0 R', "\x00\x00\x00\x00".$row($offsets[1]).$row($offsets[2]).$row($offsets[3])."\x01\x00");

        self::assertSame('real', new PdfDocument($pdf->startxref($stream), new Limits())->object(3));
    }

    public function testAnotherSubsectionAfterADamagedCount(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $extra = $pdf->object(4, '(extra)');
        $row = static fn (int $offset): string => "\x01".pack('n', $offset)."\x00";
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Index [0 4 4 (x)] /Root 1 0 R', "\x00\x00\x00\x00".$row($offsets[1]).$row($offsets[2]).$row($offsets[3]).$row($extra));
        $document = new PdfDocument($pdf->startxref($stream), new Limits());

        self::assertSame('real', $document->object(3));
        self::assertNull($document->object(4));
    }

    public function testTheTrailerOfABrokenCrossReferenceStreamIsKeptByTheScan(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(3, '(info)');

        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Size 4 /Info 3 0 R', str_repeat("\x00", 16));
        $document = new PdfDocument($pdf->startxref($stream), new Limits());

        self::assertEquals(new PdfReference(1), $document->trailer->get('Root'));
        self::assertEquals(new PdfReference(3), $document->trailer->get('Info'));
    }

    public function testAScanTakesTheFirstObjectStreamThatHoldsAnObject(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->stream(5, '/Type /ObjStm /N 1 /First 4', '3 0 (first)');
        $pdf->stream(6, '/Type /ObjStm /N 1 /First 4', '3 0 (second)');

        self::assertSame('first', new PdfDocument($pdf->bytes, new Limits())->object(3));
    }

    public function testAScanTakesACatalogWrittenAsAStreamAndNothingElse(): void
    {
        $pdf = new RawPdf();
        $pdf->object(3, '<< /Type /Pages /Kids [] >>');
        $pdf->object(4, '[/Catalog]');
        $pdf->stream(5, '/Type /Catalog /Pages 3 0 R', 'x');

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertEquals(new PdfReference(5), $document->trailer->get('Root'));
    }

    public function testALengthThatIsNoWholeNumberIsIgnored(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(3, "<< /Length 4.0 >>\nstream\nab\ncd\nendstream");

        $stream = new PdfDocument($pdf->bytes, new Limits())->object(3);

        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame("ab\ncd", $stream->raw);
    }

    public function testAStreamThatIsNoCrossReferenceStreamIsNotRead(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $row = static fn (int $offset): string => "\x01".pack('n', $offset)."\x00";
        $stream = $pdf->stream(9, '/Type /Metadata /W [1 2 1] /Size 4 /Root 1 0 R', "\x00\x00\x00\x00".$row($offsets[1]).$row($offsets[2]).$row($offsets[3]));

        self::assertSame('decoy', new PdfDocument($pdf->startxref($stream), new Limits())->object(3));
    }

    public function testCompressedObjectsAreReadFromTheirObjectStream(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $objects = $pdf->stream(5, '/Type /ObjStm /N 2 /First 8', '3 0 4 7 (real) (four)');
        $pdf->object(3, '(decoy)');
        $row = static fn (int $type, int $value, int $index): string => \chr($type).pack('n', $value).\chr($index);
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Size 6 /Root 1 0 R', $row(0, 0, 0).$row(1, $catalog, 0).$row(1, $pages, 0).$row(2, 5, 0).$row(2, 5, 1).$row(1, $objects, 0));
        $document = new PdfDocument($pdf->startxref($stream), new Limits());

        self::assertSame('real', $document->object(3));
        self::assertSame('four', $document->object(4));
    }

    public function testACompressedObjectWithoutIndexIsTheFirstOfItsStream(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $objects = $pdf->stream(5, '/Type /ObjStm /N 2 /First 8', '3 0 4 7 (real) (four)');
        $row = static fn (int $type, int $value): string => \chr($type).pack('n', $value);
        $stream = $pdf->xrefStream(9, '/W [1 2 0] /Size 6 /Root 1 0 R', $row(0, 0).$row(1, $catalog).$row(1, $pages).$row(2, 5).$row(0, 0).$row(1, $objects));

        self::assertSame('real', new PdfDocument($pdf->startxref($stream), new Limits())->object(3));
    }

    public function testANewerSectionHidesWhatAnOlderOneSaysOfTheSameObject(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $objects = $pdf->stream(5, '/Type /ObjStm /N 3 /First 12', '3 0 4 7 6 14 (old) (four) (six)');
        $row = static fn (int $type, int $value, int $index): string => \chr($type).pack('n', $value).\chr($index);
        $older = $pdf->xrefStream(9, '/W [1 2 1] /Size 7 /Root 1 0 R', $row(0, 0, 0).$row(1, $catalog, 0).$row(1, $pages, 0).$row(2, 5, 0).$row(2, 5, 1).$row(1, $objects, 0).$row(2, 5, 2));
        $three = $pdf->object(3, '(new)');
        $newer = $pdf->xrefStream(10, '/W [1 2 1] /Index [3 2 6 1] /Prev '.$older, $row(1, $three, 0).$row(0, 0, 0).$row(2, 5, 1));
        $document = new PdfDocument($pdf->startxref($newer), new Limits());

        self::assertSame('new', $document->object(3));
        self::assertNull($document->object(4));
        self::assertSame('four', $document->object(6));
    }

    public function testAnOlderSectionDoesNotMoveACompressedObject(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $old = $pdf->object(3, '(old)');
        $older = $pdf->table([0 => [null, $catalog, $pages, $old]], '<< /Size 6 /Root 1 0 R >>');
        $objects = $pdf->stream(5, '/Type /ObjStm /N 1 /First 4', '3 0 (new)');
        $row = static fn (int $type, int $value, int $index): string => \chr($type).pack('n', $value).\chr($index);
        $newer = $pdf->xrefStream(9, '/W [1 2 1] /Index [3 1 5 1] /Prev '.$older, $row(2, 5, 0).$row(1, $objects, 0));

        self::assertSame('new', new PdfDocument($pdf->startxref($newer), new Limits())->object(3));
    }

    public function testAHybridFileReadsItsCrossReferenceStreamToo(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $real = $pdf->object(3, '(real)');
        $pdf->object(3, '(decoy)');
        $row = static fn (int $offset): string => "\x01".pack('n', $offset)."\x00";
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Index [3 1]', $row($real));
        $table = $pdf->table([0 => [null, $catalog, $pages]], '<< /Size 4 /Root 1 0 R /XRefStm '.$stream.' >>');

        self::assertSame('real', new PdfDocument($pdf->startxref($table), new Limits())->object(3));
    }

    public function testDeclaredObjectsAreCountedAgainstTheLimit(): void
    {
        [$pdf, $offsets] = $this->decoyed();
        $table = $pdf->table([0 => [null, $offsets[1], $offsets[2], $offsets[3]]], '<< /Size 4 /Root 1 0 R >>');
        $bytes = $pdf->startxref($table);

        // A free entry is not counted.
        self::assertSame('real', new PdfDocument($bytes, new Limits(objects: 3))->object(3));

        $this->expectExceptionObject(LimitExceeded::because('The PDF declares more than 2 objects.'));
        new PdfDocument($bytes, new Limits(objects: 2));
    }

    public function testCompressedObjectsAreCountedAgainstTheLimit(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $row = static fn (int $type, int $value, int $index): string => \chr($type).pack('n', $value).\chr($index);
        $stream = $pdf->xrefStream(9, '/W [1 2 1] /Index [1 2 3 3] /Root 1 0 R', $row(1, $catalog, 0).$row(1, $pages, 0).$row(2, 7, 0).$row(2, 7, 1).$row(2, 7, 2));

        new PdfDocument($pdf->startxref($stream), new Limits(objects: 5));
        $this->expectException(LimitExceeded::class);
        new PdfDocument($pdf->startxref($stream), new Limits(objects: 4));
    }

    public function testAScanKeepsTheLastDefinitionAndFindsTheCatalogAndTrailer(): void
    {
        $pdf = new RawPdf();
        $pdf->object(4, '<< /Type /Catalog /Pages 2 0 R >>');
        $pdf->object(2, self::PAGES);
        $pdf->object(3, '(first)');
        $pdf->object(3, '(last)');
        $pdf->object(6, '<< /Type /Catalog /Pages 7 0 R >>');
        $pdf->append("trailer<< /Info 3 0 R >>\n%%EOF\n");

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertSame('last', $document->object(3));
        self::assertEquals(new PdfReference(4), $document->trailer->get('Root'));
        self::assertEquals(new PdfReference(3), $document->trailer->get('Info'));
    }

    public function testAScanFindsACatalogWithoutTrailer(): void
    {
        $pdf = new RawPdf();
        $pdf->object(5, '<< /Type /Catalog /Pages 2 0 R >>');
        $pdf->object(2, self::PAGES);

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertSame([], array_diff_key($document->trailer->entries, ['Root' => true]));
        self::assertEquals(new PdfReference(5), $document->trailer->get('Root'));
    }

    public function testAScanReadsTheObjectStreams(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->stream(5, '/Type /ObjStm /N 2 /First 8', '2 0 3 36 <</Type /Pages /Kids [] /Count 0>> (inner)');

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertSame('inner', $document->object(3));
        self::assertSame('Pages', $document->dictionary(new PdfReference(2))->name('Type'));
    }

    public function testAnObjectStreamHoldsNoMoreThanItsCount(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->stream(5, '/Type /ObjStm /N 1 /First 8', '3 0 4 6 (one) (two)');

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertSame('one', $document->object(3));
        self::assertNull($document->object(4));
    }

    public function testAnObjectStreamWithoutFirstOrWithADamagedHeaderHoldsNothing(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->stream(5, '/Type /ObjStm /N 1', '3 0 (one)');
        $pdf->stream(6, '/Type /ObjStm /N 2 /First 8', '4 /x 7 0 (two)');

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertNull($document->object(3));
        self::assertNull($document->object(4));
        self::assertNull($document->object(7));
    }

    public function testAnObjectStreamIsDecodedOnce(): void
    {
        $data = (string) gzcompress('3 0 4 6 (one) (two)');
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->stream(5, '/Type /ObjStm /N 2 /First 8 /Filter /FlateDecode', $data);

        $document = new PdfDocument($pdf->bytes, new Limits(totalBytes: 19));

        self::assertSame('one', $document->object(3));
        self::assertSame('two', $document->object(4));
    }

    public function testScannedObjectsAreCountedAgainstTheLimit(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);

        self::assertSame('Catalog', new PdfDocument($pdf->bytes, new Limits(objects: 2))->dictionary(new PdfReference(1))->name('Type'));
        $this->expectException(LimitExceeded::class);
        new PdfDocument($pdf->bytes, new Limits(objects: 1));
    }

    public function testTheHeaderIsLookedForInTheFirstKilobyte(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);

        $body = substr($pdf->bytes, 9);

        self::assertSame('Catalog', new PdfDocument(str_repeat(' ', 1019).'%PDF-'.$body, new Limits())->dictionary(new PdfReference(1))->name('Type'));

        $this->expectException(InvalidDocument::class);
        new PdfDocument(str_repeat(' ', 1020).'%PDF-'.$body, new Limits());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function streams(): iterable
    {
        yield 'carriage return and line feed' => ["stream\r\nline\nendstream", "line\n"];
        yield 'carriage return' => ["stream\rline\nendstream", "line\n"];
        yield 'no end of line' => ["streamline\nendstream", "line\n"];
        yield 'empty' => ["stream\n endstream", ''];
    }

    #[DataProvider('streams')]
    public function testAStreamIsAsLongAsItSays(string $stream, string $data): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(3, '<< /Length '.\strlen($data)." >>\n".$stream);

        $object = new PdfDocument($pdf->bytes, new Limits())->object(3);

        self::assertInstanceOf(PdfStream::class, $object);
        self::assertSame($data, $object->raw);
    }

    public function testAStreamOfAWrongLengthEndsAtEndstream(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(3, "<< /Length 99 >>\nstream\ndata \r\nendstream");
        $pdf->object(4, "<< /Length 4 0 R >>\nstream\nself\nendstream");

        $document = new PdfDocument($pdf->bytes, new Limits());
        $wrong = $document->object(3);
        $self = $document->object(4);

        self::assertInstanceOf(PdfStream::class, $wrong);
        self::assertSame('data ', $wrong->raw);
        self::assertInstanceOf(PdfStream::class, $self);
        self::assertSame('self', $self->raw);
        self::assertSame($self, $document->object(4));
    }

    public function testAnObjectThatCannotBeParsedFailsEachTime(): void
    {
        $pdf = new RawPdf();
        $catalog = $pdf->object(1, self::CATALOG);
        $pages = $pdf->object(2, self::PAGES);
        $deep = $pdf->object(3, '[[[[[[1]]]]]]');
        $table = $pdf->table([0 => [null, $catalog, $pages, $deep]], '<< /Size 4 /Root 1 0 R >>');
        $document = new PdfDocument($pdf->startxref($table), new Limits(depth: 3));

        foreach ([1, 2] as $attempt) {
            try {
                $document->object(3);
                self::fail('Attempt '.$attempt);
            } catch (LimitExceeded) {
                self::assertSame('Catalog', $document->dictionary(new PdfReference(1))->name('Type'));
            }
        }
    }

    public function testAReferenceIsFollowedOnce(): void
    {
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(3, '[4 0 R]');
        $pdf->object(4, '(end)');

        $document = new PdfDocument($pdf->bytes, new Limits());

        self::assertEquals([new PdfReference(4)], $document->resolve(new PdfReference(3)));
        self::assertSame('end', $document->resolve(new PdfReference(4)));
        self::assertSame(7, $document->resolve(7));
    }

    public function testTheDictionaryOfAStreamAndTheDecodingOfItsFilters(): void
    {
        // Each row of one byte after a PNG predictor byte that leaves it as it is.
        $data = (string) gzcompress("\x00h\x00e\x00l\x00l\x00o");
        $pdf = new RawPdf();
        $pdf->object(1, self::CATALOG);
        $pdf->object(2, self::PAGES);
        $pdf->object(4, '/FlateDecode');
        $pdf->object(5, '<< /Predictor 12 /Columns 1 >>');
        $pdf->stream(3, '/Filter [4 0 R] /DecodeParms [5 0 R] /DP [<< /Predictor 1 >>]', $data);

        $document = new PdfDocument($pdf->bytes, new Limits());
        $stream = $document->object(3);

        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertInstanceOf(PdfDictionary::class, $document->dictionary(new PdfReference(3)));
        self::assertEquals([new PdfReference(4)], $document->dictionary(new PdfReference(3))->get('Filter'));
        self::assertSame(['hello', null], $document->decode($stream));
        self::assertEquals(new PdfName('FlateDecode'), $document->resolve(new PdfReference(4)));
    }

    /**
     * A catalog, a page tree, object 3 as `(real)`, then a decoy of object 3 that a scan keeps.
     *
     * @return array{RawPdf, array<int, int>}
     */
    private function decoyed(): array
    {
        $pdf = new RawPdf();
        $offsets = [1 => $pdf->object(1, self::CATALOG), 2 => $pdf->object(2, self::PAGES), 3 => $pdf->object(3, '(real)')];
        $pdf->object(3, '(decoy)');

        return [$pdf, $offsets];
    }
}
