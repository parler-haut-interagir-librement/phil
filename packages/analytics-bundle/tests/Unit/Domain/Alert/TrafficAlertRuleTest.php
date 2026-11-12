<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Alert;

use Phil\AnalyticsBundle\Domain\Alert\TrafficAlert;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertKind;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertRule;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrafficAlert::class)]
#[CoversClass(TrafficAlertRule::class)]
final class TrafficAlertRuleTest extends TestCase
{
    /**
     * @param list<int> $days
     */
    private function evaluate(int $count, array $days = [100, 100], ?TrafficAlertRule $rule = null): ?TrafficAlert
    {
        return ($rule ?? new TrafficAlertRule())->evaluate(SiteId::fromString('s'), UtcDay::fromString('2026-11-09'), $count, $days);
    }

    public function testASpikeAtItsBound(): void
    {
        $alert = $this->evaluate(300);

        self::assertNotNull($alert);
        self::assertSame(TrafficAlertKind::Spike, $alert->kind);
        self::assertSame('s', $alert->site->toString());
        self::assertSame('2026-11-09', $alert->day->toString());
        self::assertSame(300, $alert->count);
        self::assertSame(100, $alert->baseline);
        self::assertNull($this->evaluate(299));
    }

    public function testADipAtItsBound(): void
    {
        $alert = $this->evaluate(30);

        self::assertNotNull($alert);
        self::assertSame(TrafficAlertKind::Dip, $alert->kind);
        self::assertNull($this->evaluate(31));
        self::assertNull($this->evaluate(301, [1000]), 'just over 30 % of the usual day is no dip');
        self::assertNotNull($this->evaluate(300, [1000]));
    }

    public function testTheBaselineIsTheMeanOfThePreviousDays(): void
    {
        self::assertSame(20, $this->evaluate(60, [10, 30])?->baseline);
    }

    public function testNoBaselineNoAlert(): void
    {
        self::assertNull($this->evaluate(1000, []));
    }

    public function testASmallSiteRaisesNoAlert(): void
    {
        self::assertNull($this->evaluate(1000, [19, 19]));
        self::assertNotNull($this->evaluate(1000, [20, 20]));
    }

    public function testTheBoundsAreTheApplicationsToChoose(): void
    {
        $rule = new TrafficAlertRule(150, 50, 1);

        self::assertSame(TrafficAlertKind::Spike, $this->evaluate(3, [2], $rule)?->kind);
        self::assertSame(TrafficAlertKind::Dip, $this->evaluate(1, [2], $rule)?->kind);
    }

    public function testTheWidestBoundsAreAccepted(): void
    {
        self::assertNotNull($this->evaluate(101, [100], new TrafficAlertRule(101, 99, 1)));
        self::assertNotNull($this->evaluate(1, [100], new TrafficAlertRule(101, 1, 1)));
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function badBounds(): iterable
    {
        yield 'a spike of 100' => [100, 30, 20, 'A spike is above 100 % of the usual day; 100 was given.'];
        yield 'a dip of 0' => [300, 0, 20, 'A dip is between 1 and 99 % of the usual day; 0 was given.'];
        yield 'a dip of 100' => [300, 100, 20, 'A dip is between 1 and 99 % of the usual day; 100 was given.'];
        yield 'no minimum' => [300, 30, 0, 'The minimum usual day is at least 1; 0 was given.'];
    }

    #[DataProvider('badBounds')]
    public function testBadBoundsAreRefused(int $spike, int $dip, int $minimum, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TrafficAlertRule($spike, $dip, $minimum);
    }
}
