<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;
use PHPUnit\Framework\Assert;

/**
 * Compares reports field by field with `assertSame`: `assertEquals` takes `0` for `null`.
 */
final class StrictReports
{
    private function __construct()
    {
    }

    /**
     * @param list<ViolationReport> $expected
     * @param list<ViolationReport> $actual
     */
    public static function assertSame(array $expected, array $actual): void
    {
        Assert::assertSame(array_map(self::fields(...), $expected), array_map(self::fields(...), $actual));
    }

    /**
     * @return list<mixed>
     */
    public static function fields(ViolationReport $report): array
    {
        return [$report->documentUri, $report->blockedUri, $report->effectiveDirective, $report->disposition, $report->sourceFile, $report->line, $report->column, $report->statusCode, $report->sample];
    }
}
