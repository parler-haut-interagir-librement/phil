<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Run;

use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\ItemResult;
use Phil\BulkActions\Run\Report;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Run\RunStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(ItemResult::class)]
#[CoversClass(ItemOutcome::class)]
final class ReportTest extends TestCase
{
    public function testItCountsTheItemsByOutcome(): void
    {
        $report = new Report(RunId::generate(), RunStatus::Finished, [
            new ItemResult('a', ItemOutcome::Succeeded),
            new ItemResult('b', ItemOutcome::Failed, 'bulk_actions.reason.error'),
            new ItemResult('c', ItemOutcome::Conflict),
            new ItemResult('d', ItemOutcome::Succeeded),
            new ItemResult('e', ItemOutcome::Failed),
        ], false);

        self::assertSame(5, $report->total());
        self::assertSame(2, $report->count(ItemOutcome::Succeeded));
        self::assertSame(0, $report->count(ItemOutcome::Denied));
        self::assertEquals([new ItemResult('b', ItemOutcome::Failed, 'bulk_actions.reason.error'), new ItemResult('e', ItemOutcome::Failed)], $report->with(ItemOutcome::Failed));
        self::assertSame(2, $report->retryable());
        self::assertSame(3, $report->retryable(true));
        self::assertSame(['pending' => 0, 'succeeded' => 2, 'denied' => 0, 'conflict' => 1, 'refused' => 0, 'failed' => 2, 'missing' => 0], $report->counts());
        self::assertSame(RunStatus::Finished, $report->status);
        self::assertFalse($report->truncated);
    }
}
