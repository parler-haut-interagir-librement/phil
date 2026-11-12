<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Application\Alert;

use Override;
use Phil\AnalyticsBundle\Application\Alert\TrafficAlertRun;
use Phil\AnalyticsBundle\Application\Contract\TrafficAlertNotifier;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlert;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertKind;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertRule;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrafficAlertRun::class)]
final class TrafficAlertRunTest extends TestCase
{
    public function testTheDayBeforeIsComparedWithTheBaselineDaysBeforeIt(): void
    {
        $store = new InMemoryCounters();
        // Ten visits a day over the seven days before the day checked, from two sources; the day before them and today are not read.
        $this->visits($store, 'spring-con', '2026-11-02', 1, 500);
        $this->visits($store, 'spring-con', '2026-11-03', 7, 5, 'direct');
        $this->visits($store, 'spring-con', '2026-11-03', 7, 5, 'search');
        $this->visits($store, 'spring-con', '2026-11-10', 1, 30);
        $this->visits($store, 'spring-con', '2026-11-11', 1, 500);
        // A steady site raises nothing.
        $this->visits($store, 'autumn', '2026-11-02', 10, 20);
        $notifier = $this->notifier();

        $alerts = new TrafficAlertRun($store, new TrafficAlertRule(300, 30, 10), $notifier, ['spring-con', 'autumn'], 7)->checkFor(UtcDay::fromString('2026-11-11'));

        self::assertCount(1, $alerts);
        self::assertSame($alerts, $notifier->alerts);
        self::assertSame('spring-con', $alerts[0]->site->value);
        self::assertSame('2026-11-10', $alerts[0]->day->value);
        self::assertSame(TrafficAlertKind::Spike, $alerts[0]->kind);
        self::assertSame(30, $alerts[0]->count);
        self::assertSame(10, $alerts[0]->baseline);
    }

    public function testADipIsRaisedAndAQuietSiteIsNot(): void
    {
        $store = new InMemoryCounters();
        $this->visits($store, 'spring-con', '2026-10-13', 28, 20);
        $this->visits($store, 'spring-con', '2026-11-10', 1, 2);
        $this->visits($store, 'quiet', '2026-10-13', 29, 2);
        $notifier = $this->notifier();

        $alerts = new TrafficAlertRun($store, new TrafficAlertRule(), $notifier, ['spring-con', 'quiet'])->checkFor(UtcDay::fromString('2026-11-11'));

        self::assertCount(1, $alerts);
        self::assertSame(TrafficAlertKind::Dip, $alerts[0]->kind);
        self::assertSame(20, $alerts[0]->baseline);
    }

    public function testTheUsualDayIsTheMeanOfTheTwentyEightDaysBeforeByDefaultAndEverySiteIsAlerted(): void
    {
        $store = new InMemoryCounters();
        // The 28th day back weighs on the mean: (300 + 27 × 20) / 28 = 30.
        $this->visits($store, 'spring-con', '2026-10-13', 1, 300);
        $this->visits($store, 'spring-con', '2026-10-14', 27, 20);
        $this->visits($store, 'spring-con', '2026-11-10', 1, 2);
        $this->visits($store, 'autumn', '2026-10-13', 28, 40);
        $this->visits($store, 'autumn', '2026-11-10', 1, 500);
        $notifier = $this->notifier();

        $alerts = new TrafficAlertRun($store, new TrafficAlertRule(), $notifier, ['spring-con', 'autumn'])->checkFor(UtcDay::fromString('2026-11-11'));

        self::assertCount(2, $alerts);
        self::assertSame(TrafficAlertKind::Dip, $alerts[0]->kind);
        self::assertSame(30, $alerts[0]->baseline);
        self::assertSame(TrafficAlertKind::Spike, $alerts[1]->kind);
        self::assertSame('autumn', $alerts[1]->site->value);
    }

    #[TestWith([6])]
    #[TestWith([367])]
    public function testTheBaselineIsBounded(int $days): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The usual day is the mean of 7 to 366 days; %d was given.', $days));

        new TrafficAlertRun(new InMemoryCounters(), new TrafficAlertRule(), $this->notifier(), [], $days);
    }

    public function testTheBoundsOfTheBaselineAreAccepted(): void
    {
        $store = new InMemoryCounters();

        self::assertSame([], new TrafficAlertRun($store, new TrafficAlertRule(), $this->notifier(), ['s'], 7)->checkFor(UtcDay::fromString('2026-11-11')));
        self::assertSame([], new TrafficAlertRun($store, new TrafficAlertRule(), $this->notifier(), ['s'], 366)->checkFor(UtcDay::fromString('2026-11-11')));
    }

    private function visits(InMemoryCounters $store, string $site, string $firstDay, int $days, int $perDay, string $source = 'direct'): void
    {
        $day = UtcDay::fromString($firstDay);

        for ($i = 0; $i < $days; ++$i) {
            for ($n = 0; $n < $perDay; ++$n) {
                $store->increment(CounterKey::of(SiteId::fromString($site), $day, MeasureNames::VISITS, 'source', $source));
            }

            $day = $day->next();
        }
    }

    /**
     * @return TrafficAlertNotifier&object{alerts: list<TrafficAlert>}
     */
    private function notifier(): TrafficAlertNotifier
    {
        return new class implements TrafficAlertNotifier {
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
    }
}
