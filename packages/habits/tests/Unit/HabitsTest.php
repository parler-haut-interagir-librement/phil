<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use DateTimeZone;
use Override;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Day;
use Phil\Habits\Event\HabitChecked;
use Phil\Habits\Event\HabitUnchecked;
use Phil\Habits\Event\StreakReached;
use Phil\Habits\Event\StrengthReached;
use Phil\Habits\Exception\HabitNotFound;
use Phil\Habits\Exception\InvalidCheckIn;
use Phil\Habits\Exception\InvalidHabit;
use Phil\Habits\Habit;
use Phil\Habits\Habits;
use Phil\Habits\HalfLifeStrength;
use Phil\Habits\Memory\InMemoryCheckInStore;
use Phil\Habits\Memory\InMemoryHabitStore;
use Phil\Habits\Milestones;
use Phil\Habits\ProgressCalculator;
use Phil\Habits\Schedule;
use Phil\Habits\StreakUnit;
use Phil\Habits\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Habits::class)]
#[CoversClass(HabitChecked::class)]
#[CoversClass(HabitUnchecked::class)]
#[CoversClass(StreakReached::class)]
#[CoversClass(StrengthReached::class)]
#[CoversClass(HabitNotFound::class)]
final class HabitsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHabitStore $habitStore;

    private InMemoryCheckInStore $checkInStore;

    private RecordingEvents $events;

    private Habits $habits;

    #[Override]
    protected function setUp(): void
    {
        // 03:30 UTC on 2026-11-02 is still 2026-11-01 in Toronto.
        $this->clock = new MockClock('2026-11-02 03:30:00', 'UTC');
        $this->habitStore = new InMemoryHabitStore();
        $this->checkInStore = new InMemoryCheckInStore();
        $this->events = new RecordingEvents();
        $this->habits = new Habits($this->habitStore, $this->checkInStore, $this->clock, $this->events, milestones: new Milestones([2, 3], [5, 10]));
    }

    public function testAHabitStartsTodayInItsMembersZone(): void
    {
        $habit = $this->start();

        self::assertSame('2026-11-01', $habit->startsOn->toString());
        self::assertSame($habit, $this->habitStore->get('habit-1'));
        self::assertSame($habit, $this->habits->get('habit-1'));
        self::assertSame('2026-11-01', $this->habits->today($habit)->toString());
        self::assertSame(8, $this->habits->start('habit-2', 'member-1', 'Water', Schedule::daily(), new DateTimeZone('UTC'), 8)->target);
        self::assertSame('2026-11-02', $this->habits->get('habit-2')->startsOn->toString());
    }

    public function testAnIdentifierIsTakenOnce(): void
    {
        $this->start();

        $this->expectExceptionObject(InvalidHabit::because('Another habit is kept under this identifier.'));

        $this->start();
    }

    public function testAnUnknownHabitIsNotFound(): void
    {
        $this->expectExceptionObject(HabitNotFound::withId('nope'));

        $this->habits->progress('nope');
    }

    public function testACheckInIsRecordedAndDispatched(): void
    {
        $this->start();
        $progress = $this->habits->checkIn('habit-1');

        self::assertSame(1, $progress->streak->current);
        $checkIn = $this->checkInStore->get('habit-1', Day::fromString('2026-11-01'));
        self::assertSame(CheckInStatus::Done, $checkIn?->status);
        self::assertSame('2026-11-02 03:30:00 UTC', $checkIn->recordedAt->format('Y-m-d H:i:s T'));
        self::assertEquals([new HabitChecked('habit-1', 'member-1', '2026-11-01'), new StrengthReached('habit-1', 'member-1', 5)], $this->events->events);
    }

    public function testMilestonesReachedAreDispatchedOnce(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');
        $this->clock->modify('+1 day');
        $this->habits->checkIn('habit-1');
        $this->clock->modify('+1 day');
        $progress = $this->habits->checkIn('habit-1');
        $this->habits->checkIn('habit-1');

        self::assertSame(3, $progress->streak->current);
        // 1 − 2^(−n/14) after n days: 5 %, 9 %, then 14 %.
        self::assertSame(14, $progress->strengthPercent());
        self::assertEquals([
            new HabitChecked('habit-1', 'member-1', '2026-11-01'),
            new StrengthReached('habit-1', 'member-1', 5),
            new HabitChecked('habit-1', 'member-1', '2026-11-02'),
            new StreakReached('habit-1', 'member-1', 2, StreakUnit::Day),
            new HabitChecked('habit-1', 'member-1', '2026-11-03'),
            new StreakReached('habit-1', 'member-1', 3, StreakUnit::Day),
            new StrengthReached('habit-1', 'member-1', 10),
            new HabitChecked('habit-1', 'member-1', '2026-11-03'),
        ], $this->events->events);
    }

    public function testADayBackIsCheckedInWithinTheBackfill(): void
    {
        $this->start();
        $this->clock->modify('+7 days');

        $progress = $this->habits->checkIn('habit-1', day: Day::fromString('2026-11-01'));

        self::assertSame(0, $progress->streak->current);
        self::assertSame(1, $progress->streak->best);

        $this->clock->modify('+1 day');
        $this->expectExceptionObject(InvalidCheckIn::because('A check-in goes back at most 7 days.'));

        $this->habits->checkIn('habit-1', day: Day::fromString('2026-11-01'));
    }

    public function testThePartsOfAHabitWithATarget(): void
    {
        $this->habits->start('habit-1', 'member-1', 'Water', Schedule::daily(), new DateTimeZone('UTC'), 8);

        $progress = $this->habits->checkIn('habit-1', CheckInStatus::Partial, 5);

        self::assertSame(5, $progress->current?->done);
        self::assertSame(5, $this->checkInStore->get('habit-1', Day::fromString('2026-11-02'))?->value);
        self::assertSame(8, $this->habits->checkIn('habit-1', CheckInStatus::Done, 8)->current?->done);
    }

    /**
     * @return iterable<string, array{CheckInStatus, ?int, ?string, string}>
     */
    public static function refusedCheckIns(): iterable
    {
        yield 'tomorrow' => [CheckInStatus::Done, null, '2026-11-02', 'A check-in is on today or a day before.'];
        yield 'before the first day' => [CheckInStatus::Done, null, '2026-10-31', 'A check-in is on or after the first day of its habit.'];
        yield 'a value without a target' => [CheckInStatus::Done, 3, null, 'A habit without a target takes no value.'];
        yield 'a part without a target' => [CheckInStatus::Partial, null, null, 'Only a habit with a target is done in part.'];
    }

    #[DataProvider('refusedCheckIns')]
    public function testACheckInOutOfTheHabitsDaysIsRefused(CheckInStatus $status, ?int $value, ?string $day, string $message): void
    {
        $this->start();

        $this->expectExceptionObject(InvalidCheckIn::because($message));

        $this->habits->checkIn('habit-1', $status, $value, null === $day ? null : Day::fromString($day));
    }

    public function testAnArchivedHabitTakesNoCheckIn(): void
    {
        $this->start();
        $this->clock->modify('+2 days');

        self::assertSame('2026-11-03', $this->habits->archive('habit-1')->archivedOn?->toString());
        self::assertSame('2026-11-03', $this->habitStore->get('habit-1')?->archivedOn?->toString());

        $this->expectExceptionObject(InvalidCheckIn::because('An archived habit takes no more check-ins.'));

        $this->habits->checkIn('habit-1');
    }

    public function testAnArchivedHabitsProgressStopsOnItsLastDay(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');
        $this->habits->archive('habit-1');

        $this->clock->modify('+10 days');

        $progress = $this->habits->progress('habit-1');

        self::assertSame(1, $progress->streak->current);
        self::assertSame('2026-11-01', $progress->current?->period->first->toString());
    }

    public function testAHabitIsChanged(): void
    {
        $this->start();

        $habit = $this->habits->change('habit-1', 'Read more', Schedule::perWeek(2));

        self::assertSame('Read more', $habit->name);
        self::assertSame('2 per week', $this->habitStore->get('habit-1')?->schedule->toString());
        self::assertSame(5, $this->habits->change('habit-1', target: 5)->target);
        self::assertNull($this->habits->change('habit-1', withoutTarget: true)->target);
    }

    public function testDaysAreFrozenAheadAndDoneDaysStay(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');
        $this->habits->checkIn('habit-1', CheckInStatus::Skipped, day: Day::fromString('2026-11-01'));
        $this->habits->checkIn('habit-1', day: Day::fromString('2026-11-01'));

        $this->clock->modify('+1 day');
        $this->habits->checkIn('habit-1', CheckInStatus::Skipped);

        $frozen = $this->habits->freeze('habit-1', Day::fromString('2026-11-01'), Day::fromString('2026-11-05'));

        self::assertSame(4, $frozen);
        self::assertSame(CheckInStatus::Done, $this->checkInStore->get('habit-1', Day::fromString('2026-11-01'))?->status);
        self::assertSame(CheckInStatus::Frozen, $this->checkInStore->get('habit-1', Day::fromString('2026-11-02'))?->status);
        self::assertSame(CheckInStatus::Frozen, $this->checkInStore->get('habit-1', Day::fromString('2026-11-05'))?->status);
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-11-06')));
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-10-31')));

        $this->clock->modify('+4 days');
        self::assertSame(2, $this->habits->checkIn('habit-1')->streak->current);
    }

    public function testAFreezeRunsUpToAYear(): void
    {
        $this->start();

        self::assertSame(366, $this->habits->freeze('habit-1', Day::fromString('2026-11-02'), Day::fromString('2027-11-02')));
        self::assertSame(1, $this->habits->freeze('habit-1', Day::fromString('2026-11-01'), Day::fromString('2026-11-01')));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedFreezes(): iterable
    {
        $range = 'A freeze runs from a day to the same day or a later one, 366 days at most.';

        yield 'backwards' => ['2026-11-05', '2026-11-04', $range];
        yield 'longer than a year' => ['2026-11-01', '2027-11-02', $range];
        yield 'too far ahead' => ['2027-11-02', '2027-11-03', 'A freeze ends at most 366 days ahead.'];
        yield 'before the first day' => ['2026-10-31', '2026-11-02', 'A check-in is on or after the first day of its habit.'];
    }

    #[DataProvider('refusedFreezes')]
    public function testAFreezeOutOfBoundsIsRefused(string $from, string $through, string $message): void
    {
        $this->start();

        $this->expectExceptionObject(InvalidCheckIn::because($message));

        $this->habits->freeze('habit-1', Day::fromString($from), Day::fromString($through));
    }

    public function testACheckInIsTakenBack(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');
        $this->habits->freeze('habit-1', Day::fromString('2026-11-04'), Day::fromString('2026-11-04'));

        $progress = $this->habits->undo('habit-1', Day::fromString('2026-11-01'));
        $this->habits->undo('habit-1', Day::fromString('2026-11-04'));
        $this->habits->undo('habit-1', Day::fromString('2026-11-04'));

        self::assertSame(0, $progress->streak->current);
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-11-01')));
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-11-04')));
        self::assertEquals([
            new HabitChecked('habit-1', 'member-1', '2026-11-01'),
            new StrengthReached('habit-1', 'member-1', 5),
            new HabitUnchecked('habit-1', 'member-1', '2026-11-01'),
            new HabitUnchecked('habit-1', 'member-1', '2026-11-04'),
        ], $this->events->events);
    }

    public function testAnArchivedHabitsCheckInsAreNotTakenBack(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');
        $this->habits->archive('habit-1');

        $this->expectExceptionObject(InvalidCheckIn::because('An archived habit takes no more check-ins.'));

        $this->habits->undo('habit-1', Day::fromString('2026-11-01'));
    }

    public function testADayBeforeTheFirstOneIsNotTakenBack(): void
    {
        $this->start();

        $this->expectExceptionObject(InvalidCheckIn::because('A check-in is on or after the first day of its habit.'));

        $this->habits->undo('habit-1', Day::fromString('2026-10-31'));
    }

    public function testADayBeyondTheBackfillIsNotTakenBack(): void
    {
        $habits = new Habits($this->habitStore, $this->checkInStore, $this->clock, backfillDays: 2);
        $habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'));

        $this->clock->modify('+5 days');

        $this->expectExceptionObject(InvalidCheckIn::because('A check-in goes back at most 2 days.'));

        $habits->undo('habit-1', Day::fromString('2026-11-02'));
    }

    public function testAnArchivedHabitsProgressLeavesOutWhatIsStoredAfterItsLastDay(): void
    {
        $habit = $this->habits->start('habit-1', 'member-1', 'Read', Schedule::perWeek(3), new DateTimeZone('UTC'));
        $this->habits->checkIn('habit-1');
        $this->habits->archive('habit-1');
        // A row a store was handed behind the habits' back, on a day of the same week after the last one.
        $this->checkInStore->save(new CheckIn('habit-1', Day::fromString('2026-11-04'), CheckInStatus::Done, null, $this->clock->now()));
        $this->clock->modify('+10 days');

        self::assertSame('2026-11-02', $habit->startsOn->toString());
        self::assertSame(1, $this->habits->progress('habit-1')->current?->done);
    }

    public function testTheHistoryOfAHabit(): void
    {
        $this->start();
        $this->clock->modify('+2 days');
        $this->habits->checkIn('habit-1', day: Day::fromString('2026-11-01'));
        $this->habits->checkIn('habit-1', CheckInStatus::Skipped);

        $history = $this->habits->history('habit-1', Day::fromString('2026-11-01'), Day::fromString('2026-11-03'));

        self::assertSame(['2026-11-01', '2026-11-03'], array_map(static fn (CheckIn $checkIn): string => $checkIn->day->toString(), $history));
    }

    public function testAMembersHabitsAreListedAndForgotten(): void
    {
        $this->start();
        $this->habits->start('habit-2', 'member-1', 'Walk', Schedule::daily(), new DateTimeZone('UTC'));
        $this->habits->start('habit-3', 'member-2', 'Walk', Schedule::daily(), new DateTimeZone('UTC'));
        $this->habits->checkIn('habit-1');
        $this->habits->checkIn('habit-3');

        self::assertSame(['habit-1', 'habit-2'], array_map(static fn (Habit $habit): string => $habit->id, $this->habits->ofMember('member-1')));
        self::assertSame(2, $this->habits->forgetMember('member-1'));
        self::assertSame([], $this->habits->ofMember('member-1'));
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-11-01')));
        self::assertNotNull($this->checkInStore->get('habit-3', Day::fromString('2026-11-02')));
        self::assertSame(0, $this->habits->forgetMember('member-1'));
    }

    public function testAHabitIsRemovedWithItsCheckIns(): void
    {
        $this->start();
        $this->habits->checkIn('habit-1');

        $this->habits->remove('habit-1');
        $this->habits->remove('habit-1');

        self::assertNull($this->habitStore->get('habit-1'));
        self::assertNull($this->checkInStore->get('habit-1', Day::fromString('2026-11-01')));
    }

    public function testWithoutADispatcherNothingIsDispatched(): void
    {
        $habits = new Habits($this->habitStore, $this->checkInStore, $this->clock, calculator: new ProgressCalculator(new HalfLifeStrength(1.0)), backfillDays: 0);
        $habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'));

        self::assertSame(100, $habits->checkIn('habit-1')->strengthPercent() + 50);
        self::assertSame([], $this->events->events);

        $this->clock->modify('+1 day');
        $this->expectExceptionObject(InvalidCheckIn::because('A check-in goes back at most 0 days.'));

        $habits->checkIn('habit-1', day: Day::fromString('2026-11-02'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidBackfills(): iterable
    {
        yield 'negative' => [-1];
        yield 'above a year' => [367];
    }

    #[DataProvider('invalidBackfills')]
    public function testABackfillOutOfBoundsIsRefused(int $days): void
    {
        $this->expectExceptionObject(InvalidHabit::because('A check-in goes back 0 to 366 days.'));

        new Habits($this->habitStore, $this->checkInStore, $this->clock, backfillDays: $days);
    }

    public function testABackfillOfAYearIsAllowed(): void
    {
        $habits = new Habits($this->habitStore, $this->checkInStore, $this->clock, backfillDays: 366);
        $habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'));

        $this->clock->modify('+366 days');

        self::assertSame(1, $habits->checkIn('habit-1', day: Day::fromString('2026-11-02'))->streak->best);
    }

    private function start(): Habit
    {
        return $this->habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('America/Toronto'));
    }
}
