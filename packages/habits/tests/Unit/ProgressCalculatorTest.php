<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidHabit;
use Phil\Habits\Habit;
use Phil\Habits\HalfLifeStrength;
use Phil\Habits\PeriodProgress;
use Phil\Habits\Progress;
use Phil\Habits\ProgressCalculator;
use Phil\Habits\Schedule;
use Phil\Habits\Streak;
use Phil\Habits\StreakUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgressCalculator::class)]
#[CoversClass(HalfLifeStrength::class)]
#[CoversClass(Progress::class)]
#[CoversClass(PeriodProgress::class)]
#[CoversClass(Streak::class)]
final class ProgressCalculatorTest extends TestCase
{
    private const string FIRST = '2026-11-02';

    public function testTheStrengthHalvesItsMemoryEveryHalfLife(): void
    {
        $model = new HalfLifeStrength();

        self::assertSame(14.0, $model->halfLife);
        self::assertEqualsWithDelta(0.5, $model->next(0.0, 1.0, 14.0), 1e-12);
        self::assertEqualsWithDelta(0.5, $model->next(1.0, 0.0, 14.0), 1e-12);
        self::assertEqualsWithDelta(0.75, $model->next(0.5, 1.0, 14.0), 1e-12);
        self::assertEqualsWithDelta(1 - 2 ** (-1 / 14), $model->next(0.0, 1.0, 1.0), 1e-12);
        self::assertEqualsWithDelta(0.4, $model->next(0.4, 0.4, 3.0), 1e-12);
        self::assertEqualsWithDelta(0.25, new HalfLifeStrength(7.0)->next(0.0, 0.5, 7.0), 1e-12);
    }

    public function testTheStrengthStaysBetweenZeroAndOne(): void
    {
        $model = new HalfLifeStrength(1.0);

        self::assertSame(1.0, $model->next(1.0, 1.0, 365.0));
        self::assertSame(1.0, $model->next(1.0, 2.0, 2000.0));
        self::assertSame(0.0, $model->next(0.0, -1.0, 2000.0));
        self::assertSame(0.0, $model->next(0.0, 0.0, 2000.0));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidHalfLives(): iterable
    {
        yield 'below a day' => [0.99];
        yield 'above a year' => [365.01];
        yield 'not a number' => [\NAN];
    }

    #[DataProvider('invalidHalfLives')]
    public function testAHalfLifeOutOfBoundsIsRefused(float $halfLife): void
    {
        $this->expectExceptionObject(InvalidHabit::because('The half-life of a strength is from 1 to 365 days.'));

        new HalfLifeStrength($halfLife);
    }

    public function testTheBoundsOfTheHalfLife(): void
    {
        self::assertSame(1.0, new HalfLifeStrength(1.0)->halfLife);
        self::assertSame(365.0, new HalfLifeStrength(365.0)->halfLife);
    }

    public function testDaysDoneInARowMakeAStreakAndTodayWaits(): void
    {
        $progress = $this->calculate(Schedule::daily(), ['2026-11-02' => 'done', '2026-11-03' => 'done', '2026-11-04' => 'done'], '2026-11-05');

        self::assertSame(3, $progress->streak->current);
        self::assertSame(3, $progress->streak->best);
        self::assertSame(StreakUnit::Day, $progress->streak->unit);
        self::assertEqualsWithDelta(1 - 2 ** (-3 / 14), $progress->strength, 1e-12);
        self::assertSame('2026-11-05', $progress->current?->period->first->toString());
        self::assertSame(0, $progress->current->done);
        self::assertSame(1, $progress->current->required);
        self::assertFalse($progress->current->isMet());
    }

    public function testTodayCountsOnceDone(): void
    {
        $progress = $this->calculate(Schedule::daily(), ['2026-11-04' => 'done', '2026-11-05' => 'done'], '2026-11-05');

        self::assertSame(2, $progress->streak->current);
        self::assertTrue($progress->current?->isMet());
        // The first two days were missed, then two were done.
        $missed = 0.0;
        $done = 1 - 2 ** (-2 / 14);
        self::assertEqualsWithDelta($missed + $done, $progress->strength, 1e-12);
    }

    public function testAMissedDayBringsTheStreakBackButTheBestStays(): void
    {
        $progress = $this->calculate(Schedule::daily(), [
            '2026-11-02' => 'done',
            '2026-11-03' => 'done',
            '2026-11-04' => 'done',
            '2026-11-06' => 'done',
        ], '2026-11-07');

        self::assertSame(1, $progress->streak->current);
        self::assertSame(3, $progress->streak->best);

        $weight = 1 - 2 ** (-1 / 14);
        $expected = 1 - (1 - $weight) ** 3;
        $expected *= 1 - $weight;
        $expected += $weight * (1 - $expected);
        self::assertEqualsWithDelta($expected, $progress->strength, 1e-12);
    }

    public function testSkippedAndFrozenDaysNeitherCountNorBreak(): void
    {
        $progress = $this->calculate(Schedule::daily(), [
            '2026-11-02' => 'done',
            '2026-11-03' => 'skipped',
            '2026-11-04' => 'frozen',
            '2026-11-05' => 'done',
        ], '2026-11-06');

        self::assertSame(2, $progress->streak->current);
        self::assertEqualsWithDelta(1 - 2 ** (-2 / 14), $progress->strength, 1e-12);
    }

    public function testAFrozenDayOfAHabitWithATargetKeepsTheStreak(): void
    {
        // A frozen day is worth the whole target, whatever the target is.
        $progress = $this->calculate(Schedule::daily(), [
            '2026-11-02' => 'done',
            '2026-11-03' => 'frozen',
        ], '2026-11-04', 4);

        self::assertSame(1, $progress->streak->current);
    }

    public function testAPartialDayMovesTheStrengthTowardsItsShare(): void
    {
        $progress = $this->calculate(Schedule::daily(), ['2026-11-02' => 'done', '2026-11-03' => 4], '2026-11-04', target: 8);

        self::assertSame(0, $progress->streak->current);
        self::assertSame(1, $progress->streak->best);
        $weight = 1 - 2 ** (-1 / 14);
        self::assertEqualsWithDelta($weight + $weight * (0.5 - $weight), $progress->strength, 1e-12);
    }

    public function testAPartialDayReachingTheTargetIsMet(): void
    {
        $progress = $this->calculate(Schedule::daily(), ['2026-11-02' => 8, '2026-11-03' => 9], '2026-11-04', target: 8);

        self::assertSame(2, $progress->streak->current);
        self::assertSame(8, $progress->current?->required);
    }

    public function testAWeekIsMetByItsCheckInsAndCountsSevenDays(): void
    {
        // From Monday 2026-11-02: a first week met, a second missed with two of three, a third met; the fourth runs.
        $progress = $this->calculate(Schedule::perWeek(3), [
            '2026-11-02' => 'done',
            '2026-11-04' => 'done',
            '2026-11-08' => 'done',
            '2026-11-10' => 'done',
            '2026-11-12' => 'done',
            '2026-11-16' => 'done',
            '2026-11-17' => 'done',
            '2026-11-18' => 'done',
            '2026-11-23' => 'done',
        ], '2026-11-24');

        self::assertSame(1, $progress->streak->current);
        self::assertSame(1, $progress->streak->best);
        self::assertSame(StreakUnit::Week, $progress->streak->unit);
        self::assertSame(1, $progress->current?->done);
        self::assertSame(3, $progress->current->required);

        // A week weighs 1 − 2^(−7/14): seven days of a half-life of fourteen.
        $weight = 1 - 2 ** (-7 / 14);
        $expected = $weight;
        $expected += $weight * (2 / 3 - $expected);
        $expected += $weight * (1 - $expected);
        self::assertEqualsWithDelta($expected, $progress->strength, 1e-12);
    }

    public function testAWeekShortOfItsCheckInsByExcusedDaysIsSpared(): void
    {
        $progress = $this->calculate(Schedule::perWeek(3), [
            '2026-11-02' => 'done',
            '2026-11-03' => 'done',
            '2026-11-04' => 'done',
            '2026-11-09' => 'done',
            '2026-11-10' => 'frozen',
            '2026-11-11' => 'skipped',
            '2026-11-16' => 'done',
            '2026-11-17' => 'done',
            '2026-11-18' => 'done',
        ], '2026-11-23');

        self::assertSame(2, $progress->streak->current);
        self::assertSame(0, $progress->current?->excusedDays);
        // Two weeks met of seven days each: one half-life.
        self::assertEqualsWithDelta(0.5, $progress->strength, 1e-12);
    }

    public function testAWeekShortEvenWithItsExcusedDaysIsMissed(): void
    {
        $progress = $this->calculate(Schedule::perWeek(3), [
            '2026-11-02' => 'done',
            '2026-11-03' => 'skipped',
        ], '2026-11-09');

        self::assertSame(0, $progress->streak->current);
        self::assertEqualsWithDelta((1 - 2 ** (-7 / 14)) / 3, $progress->strength, 1e-12);
        self::assertSame(0, $progress->current?->excusedDays);
    }

    public function testExcusedDaysAreCountedInTheirPeriod(): void
    {
        $progress = $this->calculate(Schedule::perWeek(3), ['2026-11-02' => 'done', '2026-11-03' => 'skipped', '2026-11-04' => 'frozen'], '2026-11-05');

        self::assertSame(1, $progress->current?->done);
        self::assertSame(2, $progress->current->excusedDays);
        self::assertEqualsWithDelta(1 / 3, $progress->current->completion(), 1e-12);
    }

    public function testCheckInsOffTheScheduleAreLeftOut(): void
    {
        // Mondays and Wednesdays from Monday 2026-11-02; Tuesday's check-in counts for nothing.
        $progress = $this->calculate(Schedule::onWeekdays([1, 3]), ['2026-11-02' => 'done', '2026-11-03' => 'done', '2026-11-04' => 'done'], '2026-11-08');

        self::assertSame(2, $progress->streak->current);
        self::assertSame('2026-11-04', $progress->current?->period->first->toString());
        self::assertEqualsWithDelta(1 - 2 ** (-7 / 14), $progress->strength, 1e-12);
    }

    public function testAnArchivedHabitStopsOnItsLastDay(): void
    {
        $habit = $this->habit(Schedule::daily())->archive(Day::fromString('2026-11-03'));
        $checkIns = $this->checkIns(['2026-11-02' => 'done', '2026-11-03' => 'done', '2026-11-04' => 'done']);

        $progress = new ProgressCalculator()->calculate($habit, $checkIns, Day::fromString('2026-11-20'));

        self::assertSame(2, $progress->streak->current);
        self::assertSame('2026-11-03', $progress->current?->period->first->toString());

        $archivedLater = $this->habit(Schedule::daily())->archive(Day::fromString('2026-11-30'));
        self::assertSame('2026-11-04', new ProgressCalculator()->calculate($archivedLater, $checkIns, Day::fromString('2026-11-04'))->current?->period->first->toString());
    }

    public function testAHabitNotStartedHasNoProgress(): void
    {
        $progress = $this->calculate(Schedule::daily(), [], '2026-11-01');

        self::assertSame(0, $progress->streak->current);
        self::assertSame(0, $progress->streak->best);
        self::assertSame(0.0, $progress->strength);
        self::assertNull($progress->current);
        self::assertSame(0, $progress->strengthPercent());
    }

    public function testAnotherStrengthModelIsUsed(): void
    {
        $habit = $this->habit(Schedule::daily());
        $progress = new ProgressCalculator(new HalfLifeStrength(1.0))->calculate($habit, $this->checkIns(['2026-11-02' => 'done']), Day::fromString('2026-11-03'));

        self::assertEqualsWithDelta(0.5, $progress->strength, 1e-12);
        self::assertSame(50, $progress->strengthPercent());
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function percents(): iterable
    {
        yield 'zero' => [0.0, 0];
        yield 'rounded down' => [0.124, 12];
        yield 'rounded up' => [0.125, 13];
        yield 'nearly whole' => [0.996, 100];
        yield 'whole' => [1.0, 100];
    }

    #[DataProvider('percents')]
    public function testTheStrengthIsShownAsAWholePercentage(float $strength, int $percent): void
    {
        self::assertSame($percent, new Progress(new Streak(0, 0, StreakUnit::Day), $strength, null)->strengthPercent());
    }

    /**
     * @param array<string, int|string> $days a status, or the value of a partial check-in
     */
    private function calculate(Schedule $schedule, array $days, string $today, ?int $target = null): Progress
    {
        return new ProgressCalculator()->calculate($this->habit($schedule, $target), $this->checkIns($days), Day::fromString($today));
    }

    private function habit(Schedule $schedule, ?int $target = null): Habit
    {
        return new Habit('habit-1', 'member-1', 'Read', $schedule, new DateTimeZone('UTC'), Day::fromString(self::FIRST), $target);
    }

    /**
     * @param array<string, int|string> $days
     *
     * @return list<CheckIn>
     */
    private function checkIns(array $days): array
    {
        $checkIns = [];

        foreach (array_reverse($days, true) as $day => $status) {
            $checkIns[] = \is_int($status)
                ? new CheckIn('habit-1', Day::fromString($day), CheckInStatus::Partial, $status, new DateTimeImmutable())
                : new CheckIn('habit-1', Day::fromString($day), CheckInStatus::from($status), null, new DateTimeImmutable());
        }

        return $checkIns;
    }
}
