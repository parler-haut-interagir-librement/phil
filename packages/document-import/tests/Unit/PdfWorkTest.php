<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Internal\Pdf\PdfBudget;
use Phil\DocumentImport\Internal\Pdf\PdfContent;
use Phil\DocumentImport\Internal\Pdf\PdfLine;
use Phil\DocumentImport\Internal\Pdf\PdfReader;
use Phil\DocumentImport\Internal\Pdf\PdfRun;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Tests\Unit\Fixture\Pdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The work a PDF may ask for: content operators counted each time a form is drawn, links checked against the runs
 * of their page, long lines read once.
 */
#[CoversClass(PdfBudget::class)]
#[CoversClass(PdfContent::class)]
#[CoversClass(PdfReader::class)]
#[CoversClass(PdfLine::class)]
#[CoversClass(Limits::class)]
final class PdfWorkTest extends TestCase
{
    public function testTheBudgetCountsUpToItsLimit(): void
    {
        $budget = new PdfBudget(3);
        $budget->spend(2);
        $budget->spend(1);

        $this->expectExceptionObject(LimitExceeded::because('The PDF asks for more than 3 operations to read its pages.'));
        $budget->spend(1);
    }

    public function testTheDefaultLimitIsAMillionOperations(): void
    {
        self::assertSame(1_000_000, new Limits()->operations);
        self::assertSame(7, new Limits(operations: 7)->operations);
    }

    public function testFormsThatDrawOneAnotherAreRefused(): void
    {
        $bytes = $this->bomb(10, 3);

        // 4 operators of the innermost form drawn 1 000 times, 10 for each of the 111 forms above it, 1 on the page.
        self::assertCount(1, new DocumentImporter(limits: new Limits(operations: 5_111))->import($bytes, Format::Pdf)->document->blocks);

        $this->expectExceptionObject(LimitExceeded::because('The PDF asks for more than 5110 operations to read its pages.'));
        new DocumentImporter(limits: new Limits(operations: 5_110))->import($bytes, Format::Pdf);
    }

    public function testTheOperationsOfEveryPageCount(): void
    {
        $pdf = new Pdf()->page('BT /F1 12 Tf 72 700 Td (One) Tj ET')->page('BT /F1 12 Tf 72 700 Td (Two) Tj ET')->bytes();

        self::assertCount(2, new DocumentImporter(limits: new Limits(operations: 10))->import($pdf, Format::Pdf)->document->blocks);

        $this->expectException(LimitExceeded::class);
        new DocumentImporter(limits: new Limits(operations: 9))->import($pdf, Format::Pdf);
    }

    public function testEachLinkCountsTheRunsItIsCheckedAgainst(): void
    {
        $link = '<< /Type /Annot /Subtype /Link /Rect [70 690 200 720] /A << /S /URI /URI (https://example.org) >> >>';
        $pdf = new Pdf();
        $first = $pdf->add($link);
        $second = $pdf->add($link);
        $bytes = $pdf->page('BT /F1 12 Tf 72 700 Td (One) Tj ( two) Tj ET', ' /Annots ['.$first.' 0 R '.$second.' 0 R]')->bytes();

        // 6 operators, then 2 runs for each of the two links.
        self::assertSame(['text' => '<a href="https://example.org">One two</a>'], new DocumentImporter(limits: new Limits(operations: 10))->import($bytes, Format::Pdf)->document->blocks[0]->data);

        $this->expectExceptionObject(LimitExceeded::because('The PDF asks for more than 9 operations to read its pages.'));
        new DocumentImporter(limits: new Limits(operations: 9))->import($bytes, Format::Pdf);
    }

    public function testALineOfManyRunsKeepsItsText(): void
    {
        $line = new PdfLine(0, 0.0, 100.0, new PdfRun('a', 0.0, 5.0, 100.0, 10.0));

        for ($index = 1; $index < 2_000; ++$index) {
            $line->add(new PdfRun(0 === $index % 2 ? 'a' : 'b', 5.0 * $index, 5.0 * $index + 5.0, 100.0, 10.0));
        }

        $line->add(new PdfRun('c', 20_000.0, 20_005.0, 100.0, 10.0));

        self::assertSame(str_repeat('ab', 1_000).' c', $line->text());
        self::assertCount(2_002, $line->runs);
    }

    private function bomb(int $times, int $levels): string
    {
        $pdf = new Pdf();
        $form = $pdf->add("<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Length 30 >>\nstream\nBT /F1 12 Tf (a) Tj ET\nendstream");

        for ($level = 1; $level <= $levels; ++$level) {
            $content = str_repeat('/X Do ', $times);
            $form = $pdf->add('<< /Type /XObject /Subtype /Form /BBox [0 0 10 10] /Resources << /Font << /F1 3 0 R >> /XObject << /X '.$form.' 0 R >> >> /Length '.\strlen($content)." >>\nstream\n".$content."\nendstream");
        }

        return $pdf->page('/X Do', resources: ' /XObject << /X '.$form.' 0 R >>')->bytes();
    }
}
