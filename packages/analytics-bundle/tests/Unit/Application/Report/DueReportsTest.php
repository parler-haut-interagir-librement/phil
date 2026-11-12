<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Report;

use Override;
use Phil\AnalyticsBundle\Application\Contract\ReportSender;
use Phil\AnalyticsBundle\Application\Contract\ReportSubscriptions;
use Phil\AnalyticsBundle\Application\Report\DueReports;
use Phil\AnalyticsBundle\Domain\Report\ReportCadence;
use Phil\AnalyticsBundle\Domain\Report\ReportPeriod;
use Phil\AnalyticsBundle\Domain\Report\ReportSubscription;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DueReports::class)]
final class DueReportsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $sent = [];

    /**
     * @return iterable<string, array{string, list<ReportCadence>}>
     */
    public static function days(): iterable
    {
        yield 'a Monday' => ['2026-11-09', [ReportCadence::Weekly]];
        yield 'a first of the month' => ['2026-10-01', [ReportCadence::Monthly]];
        yield 'a Monday that is a first' => ['2026-06-01', [ReportCadence::Weekly, ReportCadence::Monthly]];
        yield 'a Wednesday' => ['2026-11-11', []];
        yield 'a Sunday' => ['2026-11-15', []];
    }

    /**
     * @param list<ReportCadence> $due
     */
    #[DataProvider('days')]
    public function testTheCadencesDueOnADay(string $day, array $due): void
    {
        self::assertSame($due, DueReports::cadencesDueOn(UtcDay::fromString($day)));
    }

    public function testEachSubscriptionOfADueCadenceReceivesTheLastWholePeriod(): void
    {
        $sent = $this->reports()->sendFor(UtcDay::fromString('2026-06-01'));

        self::assertSame(3, $sent);
        self::assertSame([
            'w1 2026-05-25 2026-05-31',
            'w2 2026-05-25 2026-05-31',
            'm1 2026-05-01 2026-05-31',
        ], $this->sent);
    }

    public function testNothingIsSentOnADayWithoutAReport(): void
    {
        self::assertSame(0, $this->reports()->sendFor(UtcDay::fromString('2026-11-11')));
        self::assertSame([], $this->sent);
    }

    private function reports(): DueReports
    {
        $test = $this;

        return new DueReports(
            new class implements ReportSubscriptions {
                #[Override]
                public function ofCadence(ReportCadence $cadence): iterable
                {
                    $site = SiteId::fromString('s');

                    return ReportCadence::Weekly === $cadence
                        ? [new ReportSubscription('w1', $site, $cadence, 'r1'), new ReportSubscription('w2', $site, $cadence, 'r2')]
                        : [new ReportSubscription('m1', $site, $cadence, 'r1')];
                }
            },
            new readonly class($test) implements ReportSender {
                public function __construct(
                    private DueReportsTest $test,
                ) {
                }

                #[Override]
                public function send(ReportSubscription $subscription, ReportPeriod $period): void
                {
                    $this->test->record($subscription->id.' '.$period->from->value.' '.$period->to->value);
                }
            },
        );
    }

    /**
     * @internal the sender of the test
     */
    public function record(string $report): void
    {
        $this->sent[] = $report;
    }
}
