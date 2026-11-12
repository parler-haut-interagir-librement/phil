<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit;

use Phil\BrokenFlowAlerts\BrokenFlowRule;
use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;
use Phil\BrokenFlowAlerts\FlowStatus;
use Phil\BrokenFlowAlerts\Internal\PoissonTail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrokenFlowRule::class)]
#[CoversClass(FlowStatus::class)]
#[CoversClass(PoissonTail::class)]
#[CoversClass(InvalidFlowValue::class)]
final class BrokenFlowRuleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $rule = new BrokenFlowRule();

        self::assertSame(28, $rule->baselineDays);
        self::assertSame(7, $rule->windowDays);
        self::assertSame(0.001, $rule->significance);
        self::assertTrue($rule->weekly);
        self::assertSame(35, $rule->historyDays());
    }

    public function testABusyFlowIsBrokenAfterASingleSilentDay(): void
    {
        $status = new BrokenFlowRule()->evaluate([...array_fill(0, 34, 20), 0]);

        self::assertTrue($status->broken);
        self::assertSame(1, $status->days);
        self::assertSame(0, $status->observed);
        self::assertEqualsWithDelta(20.0, $status->expected, 1e-9);
        self::assertEqualsWithDelta(exp(-20.0), $status->probability, 1e-15);
    }

    /**
     * Two a day: one silent day happens in a working flow (e^-2), four do not (e^-8).
     */
    public function testARareFlowNeedsALongerSilence(): void
    {
        $rule = new BrokenFlowRule();

        self::assertFalse($rule->evaluate([...array_fill(0, 34, 2), 0])->broken);
        self::assertFalse($rule->evaluate([...array_fill(0, 32, 2), 0, 0, 0])->broken);

        $status = $rule->evaluate([...array_fill(0, 31, 2), 0, 0, 0, 0]);

        self::assertTrue($status->broken);
        self::assertSame(4, $status->days);
        self::assertEqualsWithDelta(8.0, $status->expected, 1e-9);
    }

    public function testAFlowWhoseUsualDayIsZeroIsNeverBroken(): void
    {
        $status = new BrokenFlowRule()->evaluate(array_fill(0, 35, 0));

        self::assertFalse($status->broken);
        self::assertSame(1.0, $status->probability);
        self::assertSame(1, $status->days);
    }

    public function testAFlowAtItsRhythmIsNotBroken(): void
    {
        $status = new BrokenFlowRule()->evaluate(array_fill(0, 35, 10));

        self::assertFalse($status->broken);
        self::assertSame(1.0, $status->probability);
    }

    /**
     * A drop, not only a silence: a tenth of the usual completions over a few days is broken too.
     */
    public function testAFarLowerCountIsBroken(): void
    {
        $status = new BrokenFlowRule()->evaluate([...array_fill(0, 32, 50), 5, 5, 5]);

        self::assertTrue($status->broken);
        self::assertSame(15, $status->observed);
        self::assertEqualsWithDelta(150.0, $status->expected, 1e-9);
        self::assertSame(3, $status->days);
    }

    /**
     * A shop closed on Sundays: with a weekly rhythm, a silent Sunday is its usual Sunday; without one, it is a drop.
     */
    public function testEachWeekdayHasItsOwnRhythm(): void
    {
        $week = [30, 30, 30, 30, 30, 30, 0];
        $history = [...$week, ...$week, ...$week, ...$week, ...$week];

        self::assertFalse(new BrokenFlowRule()->evaluate($history)->broken);

        $flat = new BrokenFlowRule(weekly: false)->evaluate($history);

        self::assertTrue($flat->broken);
        self::assertSame(1, $flat->days);
        self::assertEqualsWithDelta(180 / 7, $flat->expected, 1e-9);
    }

    /**
     * The window day of index 28 is a weekday seen at indexes 21, 14, 7 and 0 of the baseline, and no other.
     */
    public function testTheRhythmOfADayIsTheMeanOfTheSameWeekdays(): void
    {
        $baseline = array_fill(0, 28, 10);

        foreach ([0, 7, 14, 21] as $sameWeekday) {
            $baseline[$sameWeekday] = 40;
        }

        $status = new BrokenFlowRule(windowDays: 1)->evaluate([...$baseline, 0]);

        self::assertEqualsWithDelta(40.0, $status->expected, 1e-9);
    }

    public function testTheLastDayOfAWeeklyWindowReadsItsOwnWeekday(): void
    {
        $baseline = array_fill(0, 28, 10);

        foreach ([6, 13, 20, 27] as $sameWeekday) {
            $baseline[$sameWeekday] = 40;
        }

        // Index 34 is six days after index 28: the weekday of index 27, 20, 13 and 6.
        $status = new BrokenFlowRule()->evaluate([...$baseline, 10, 10, 10, 10, 10, 10, 0]);

        self::assertSame(1, $status->days);
        self::assertEqualsWithDelta(40.0, $status->expected, 1e-9);
    }

    public function testAShortBaselineHasAFlatRhythm(): void
    {
        $baseline = [70, 0, 0, 0, 0, 0, 0, 0, 0, 0];

        $status = new BrokenFlowRule(baselineDays: 10, windowDays: 1)->evaluate([...$baseline, 0]);

        self::assertEqualsWithDelta(7.0, $status->expected, 1e-9);
    }

    public function testAFortnightIsEnoughForAWeeklyRhythm(): void
    {
        $baseline = [...array_fill(0, 7, 0), 70, ...array_fill(0, 6, 0)];
        $baseline[0] = 70;

        $status = new BrokenFlowRule(baselineDays: 14, windowDays: 1)->evaluate([...$baseline, 0]);

        self::assertEqualsWithDelta(70.0, $status->expected, 1e-9);
    }

    /**
     * The oldest day of the baseline counts as much as the others of its weekday: index 14 reads indexes 7 and 0.
     */
    public function testTheOldestBaselineDayIsOneOfTheSameWeekdays(): void
    {
        $baseline = array_fill(0, 14, 0);
        $baseline[0] = 30;
        $baseline[7] = 70;

        $status = new BrokenFlowRule(baselineDays: 14, windowDays: 1)->evaluate([...$baseline, 0]);

        self::assertEqualsWithDelta(50.0, $status->expected, 1e-9);
    }

    /**
     * A window longer than a week reaches back two weeks for its oldest days: index 21 reads indexes 7 and 0 again.
     */
    public function testAWindowOverAWeekReadsTheSameWeekdaysOfTheBaseline(): void
    {
        $baseline = array_fill(0, 14, 0);
        $baseline[0] = 30;
        $baseline[7] = 70;

        $status = new BrokenFlowRule(baselineDays: 14, windowDays: 8)->evaluate([...$baseline, 50, 0, 0, 0, 0, 0, 0, 0]);

        self::assertSame(1, $status->days);
        self::assertSame(0, $status->observed);
        self::assertEqualsWithDelta(50.0, $status->expected, 1e-9);
    }

    /**
     * A normal last day does not hide the silent days before it: the stretch of three days is the quietest.
     */
    public function testTheQuietestStretchIsReported(): void
    {
        $status = new BrokenFlowRule(windowDays: 3)->evaluate([...array_fill(0, 28, 20), 0, 0, 20]);

        self::assertTrue($status->broken);
        self::assertSame(3, $status->days);
        self::assertSame(20, $status->observed);
        self::assertEqualsWithDelta(60.0, $status->expected, 1e-9);
        self::assertEqualsWithDelta(PoissonTail::atMost(20, 60.0), $status->probability, 1e-15);
    }

    public function testASignificanceJustAboveTheProbabilityBreaks(): void
    {
        $history = [...array_fill(0, 34, 5), 0];

        self::assertFalse(new BrokenFlowRule(significance: exp(-5.0))->evaluate($history)->broken);
        self::assertTrue(new BrokenFlowRule(significance: exp(-5.0) * 1.0001)->evaluate($history)->broken);
    }

    /**
     * @return iterable<string, array{int, int, float, string}>
     */
    public static function refusedSettings(): iterable
    {
        yield 'short baseline' => [6, 7, 0.001, 'The baseline is 7 to 366 days; 6 was given.'];
        yield 'long baseline' => [367, 7, 0.001, 'The baseline is 7 to 366 days; 367 was given.'];
        yield 'empty window' => [28, 0, 0.001, 'The window is 1 to 31 days; 0 was given.'];
        yield 'long window' => [28, 32, 0.001, 'The window is 1 to 31 days; 32 was given.'];
        yield 'zero significance' => [28, 7, 0.0, 'The significance is above 0 and under 0.5; 0 was given.'];
        yield 'half significance' => [28, 7, 0.5, 'The significance is above 0 and under 0.5; 0.5 was given.'];
        yield 'not a number' => [28, 7, \NAN, 'The significance is above 0 and under 0.5; a value that is not a finite number was given.'];
        yield 'infinite' => [28, 7, \INF, 'The significance is above 0 and under 0.5; a value that is not a finite number was given.'];
    }

    #[DataProvider('refusedSettings')]
    public function testARefusedSettingSaysWhy(int $baseline, int $window, float $significance, string $message): void
    {
        $this->expectException(InvalidFlowValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new BrokenFlowRule($baseline, $window, $significance);
    }

    public function testTheBoundsAreAccepted(): void
    {
        self::assertSame(373, new BrokenFlowRule(366, 7)->historyDays());
        self::assertSame(38, new BrokenFlowRule(7, 31)->historyDays());
        self::assertSame(8, new BrokenFlowRule(7, 1, 0.4999)->historyDays());
    }

    public function testAHistoryOfTheWrongLengthIsRefused(): void
    {
        $this->expectException(InvalidFlowValue::class);
        $this->expectExceptionMessageIsOrContains('The rule reads 35 days of history; 34 were given.');

        new BrokenFlowRule()->evaluate(array_fill(0, 34, 1));
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(InvalidFlowValue::class);
        $this->expectExceptionMessageIsOrContains('A day never has fewer than zero completions.');

        new BrokenFlowRule()->evaluate([...array_fill(0, 34, 1), -1]);
    }
}
