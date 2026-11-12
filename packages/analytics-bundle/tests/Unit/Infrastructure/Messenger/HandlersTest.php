<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\Messenger;

use Override;
use Phil\AnalyticsBundle\Application\Alert\TrafficAlertRun;
use Phil\AnalyticsBundle\Application\Contract\CounterRollup;
use Phil\AnalyticsBundle\Application\Contract\ReportSender;
use Phil\AnalyticsBundle\Application\Contract\ReportSubscriptions;
use Phil\AnalyticsBundle\Application\Contract\TrafficAlertNotifier;
use Phil\AnalyticsBundle\Application\Report\DueReports;
use Phil\AnalyticsBundle\Application\Retention\CounterRetentionRun;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlert;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertKind;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertRule;
use Phil\AnalyticsBundle\Domain\Report\ReportCadence;
use Phil\AnalyticsBundle\Domain\Report\ReportPeriod;
use Phil\AnalyticsBundle\Domain\Report\ReportSubscription;
use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use Phil\AnalyticsBundle\Infrastructure\BrokenFlow\GoalCompletionHistory;
use Phil\AnalyticsBundle\Infrastructure\Messenger\ApplyCounterRetentionHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\CheckBrokenFlowsHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\CheckTrafficAlertsHandler;
use Phil\AnalyticsBundle\Infrastructure\Messenger\SendDueReportsHandler;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\BrokenFlowAlerts\BrokenFlowWatch;
use Phil\BrokenFlowAlerts\Contract\AlertSender;
use Phil\BrokenFlowAlerts\FlowAlert;
use Phil\BrokenFlowAlerts\FlowAlertKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Each daily message, handled on the UTC day the clock reads.
 */
#[CoversClass(ApplyCounterRetentionHandler::class)]
#[CoversClass(SendDueReportsHandler::class)]
#[CoversClass(CheckBrokenFlowsHandler::class)]
#[CoversClass(CheckTrafficAlertsHandler::class)]
final class HandlersTest extends TestCase
{
    /**
     * Late on Sunday in Montréal is already Monday in UTC: the weekly reports are due.
     */
    private const string NOW = '2026-11-08 21:00:00 America/Toronto';

    public function testTheRetentionRunsForTheUtcDay(): void
    {
        $rollup = new class implements CounterRollup {
            public ?string $before = null;

            #[Override]
            public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void
            {
                $this->before = $firstDailyDay->value;
            }

            #[Override]
            public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array
            {
                return [];
            }
        };

        new ApplyCounterRetentionHandler(new CounterRetentionRun($rollup, new CounterRetention(1), SmallNumberThreshold::of(2)), new MockClock(self::NOW))();

        self::assertSame('2026-10-01', $rollup->before);
    }

    public function testTheReportsDueOnTheUtcDayAreSent(): void
    {
        $sender = new class implements ReportSender {
            /**
             * @var list<string>
             */
            public array $periods = [];

            #[Override]
            public function send(ReportSubscription $subscription, ReportPeriod $period): void
            {
                $this->periods[] = $period->from->value;
            }
        };
        $subscriptions = new class implements ReportSubscriptions {
            #[Override]
            public function ofCadence(ReportCadence $cadence): iterable
            {
                yield new ReportSubscription('w', SiteId::fromString('s'), $cadence, 'r');
            }
        };

        new SendDueReportsHandler(new DueReports($subscriptions, $sender), new MockClock(self::NOW))();

        self::assertSame(['2026-11-02'], $sender->periods);
    }

    public function testTheWatchedGoalsAreChecked(): void
    {
        $store = new InMemoryCounters();
        $clock = new MockClock('2026-11-12 08:00:00 UTC');
        $day = UtcDay::fromString('2026-10-06');

        // Twenty tickets a day for 36 days, then none on the 11th.
        for ($i = 0; $i < 36; ++$i) {
            for ($n = 0; $n < 20; ++$n) {
                $store->increment(CounterKey::of(SiteId::fromString('s'), $day, 'goals', 'goal', 'ticket'));
            }

            $day = $day->next();
        }

        $sender = new class implements AlertSender {
            /**
             * @var list<FlowAlert>
             */
            public array $alerts = [];

            #[Override]
            public function send(FlowAlert $alert): void
            {
                $this->alerts[] = $alert;
            }
        };
        $goals = new GoalCompletionHistory($store, ['checkout' => ['site' => 's', 'goal' => 'ticket', 'label' => 'Tickets']]);

        new CheckBrokenFlowsHandler(new BrokenFlowWatch($goals, $sender, $clock), $goals)();

        self::assertCount(1, $sender->alerts);
        self::assertSame(FlowAlertKind::Broken, $sender->alerts[0]->kind);
        self::assertSame('2026-11-11', $sender->alerts[0]->day);
        self::assertSame('Tickets', $sender->alerts[0]->flow->label);
    }

    public function testTheTrafficOfTheDayBeforeIsChecked(): void
    {
        $store = new InMemoryCounters();
        $day = UtcDay::fromString('2026-11-01');

        // Twenty visits a day for a week, then a hundred on the 8th: the clock reads the 9th in UTC.
        for ($i = 0; $i < 8; ++$i) {
            for ($n = 0; $n < (7 === $i ? 100 : 20); ++$n) {
                $store->increment(CounterKey::of(SiteId::fromString('s'), $day, 'visits', 'source', 'direct'));
            }

            $day = $day->next();
        }

        $notifier = new class implements TrafficAlertNotifier {
            /**
             * @var list<TrafficAlert>
             */
            public array $alerts = [];

            #[Override]
            public function notify(TrafficAlert $alert): void
            {
                $this->alerts[] = $alert;
            }
        };

        new CheckTrafficAlertsHandler(new TrafficAlertRun($store, new TrafficAlertRule(), $notifier, ['s'], 7), new MockClock(self::NOW))();

        self::assertCount(1, $notifier->alerts);
        self::assertSame('2026-11-08', $notifier->alerts[0]->day->value);
        self::assertSame(TrafficAlertKind::Spike, $notifier->alerts[0]->kind);
    }
}
