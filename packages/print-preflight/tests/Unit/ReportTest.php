<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Report;
use Phil\PrintPreflight\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
final class ReportTest extends TestCase
{
    public function testAnEmptyReportPassesAndIsClean(): void
    {
        $report = new Report();

        self::assertTrue($report->passes());
        self::assertTrue($report->isClean());
        self::assertCount(0, $report);
        self::assertSame([], $report->toArray());
    }

    public function testFindingsAreSortedBySeverity(): void
    {
        $notice = Finding::of(Rule::ColourRgbInCmyk, 'n', 1);
        $warning = Finding::of(Rule::ImageBelowTarget, 'w', 2);
        $error = Finding::of(Rule::FontRestricted, 'e');
        $report = new Report([$notice, $warning, $error]);

        self::assertSame([$error], $report->errors());
        self::assertSame([$warning], $report->warnings());
        self::assertSame([$notice], $report->notices());
        self::assertFalse($report->passes());
        self::assertFalse($report->isClean());
        self::assertCount(3, $report);
        self::assertSame([$notice->toArray(), $warning->toArray(), $error->toArray()], $report->toArray());
    }

    public function testWarningsPassButAreNotClean(): void
    {
        $report = new Report([Finding::of(Rule::ImageBelowTarget, 'w', 1), Finding::of(Rule::ColourRgbInCmyk, 'n', 1)]);

        self::assertTrue($report->passes());
        self::assertFalse($report->isClean());
        self::assertTrue(new Report([Finding::of(Rule::ColourRgbInCmyk, 'n', 1)])->isClean());
    }

    public function testFindingsAreFoundByPage(): void
    {
        $document = Finding::of(Rule::FontRestricted, 'e');
        $first = Finding::of(Rule::ColourRgbInCmyk, 'n', 1);
        $second = Finding::of(Rule::ImageBelowTarget, 'w', 2);
        $report = new Report([$document, $first, $second]);

        self::assertSame([$document], $report->forPage(null));
        self::assertSame([$second], $report->forPage(2));
        self::assertSame([], $report->forPage(3));
    }
}
