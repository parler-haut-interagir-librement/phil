<?php

declare(strict_types=1);

namespace Phil\Habits;

use DateTimeZone;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Event\HabitChecked;
use Phil\Habits\Event\HabitUnchecked;
use Phil\Habits\Event\StreakReached;
use Phil\Habits\Event\StrengthReached;
use Phil\Habits\Exception\HabitNotFound;
use Phil\Habits\Exception\InvalidCheckIn;
use Phil\Habits\Exception\InvalidHabit;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The habits of members: starting one, checking it in on a day, freezing days ahead, taking a check-in back, and
 * reading where it stands.
 *
 * Days are the member's, in the habit's time zone, from the clock it is given; instants are recorded in UTC. A
 * check-in goes back at most a number of days — seven by default —, and never ahead of today; a freeze may run up to a
 * year ahead. After each check-in, the dispatcher it is given, if any, receives `HabitChecked`, then `StreakReached`
 * and `StrengthReached` for the milestones the check-in reached.
 */
final readonly class Habits
{
    public const int MAX_BACKFILL_DAYS = 366;

    public const int MAX_FREEZE_DAYS = 366;

    /**
     * @throws InvalidHabit
     */
    public function __construct(
        private HabitStore $habits,
        private CheckInStore $checkIns,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private ProgressCalculator $calculator = new ProgressCalculator(),
        private Milestones $milestones = new Milestones(),
        private int $backfillDays = 7,
    ) {
        if ($backfillDays < 0 || $backfillDays > self::MAX_BACKFILL_DAYS) {
            throw InvalidHabit::because(\sprintf('A check-in goes back 0 to %d days.', self::MAX_BACKFILL_DAYS));
        }
    }

    /**
     * Starts a habit today, in the member's time zone.
     *
     * @throws InvalidHabit when the identifier is taken, or a value is out of bounds
     */
    public function start(string $id, string $member, string $name, Schedule $schedule, DateTimeZone $timeZone, ?int $target = null): Habit
    {
        if ($this->habits->get($id) instanceof Habit) {
            throw InvalidHabit::because('Another habit is kept under this identifier.');
        }

        $habit = new Habit($id, $member, $name, $schedule, $timeZone, Day::of($this->clock->now(), $timeZone), $target);
        $this->habits->save($habit);

        return $habit;
    }

    /**
     * @throws HabitNotFound
     */
    public function get(string $id): Habit
    {
        return $this->habits->get($id) ?? throw HabitNotFound::withId($id);
    }

    /**
     * The day it is for a habit's member.
     */
    public function today(Habit $habit): Day
    {
        return Day::of($this->clock->now(), $habit->timeZone);
    }

    /**
     * Renames a habit, or changes its schedule or target; its check-ins stay, and its progress is worked out again
     * under the new schedule.
     *
     * @throws HabitNotFound
     * @throws InvalidHabit
     */
    public function change(string $id, ?string $name = null, ?Schedule $schedule = null, ?int $target = null, bool $withoutTarget = false): Habit
    {
        $habit = $this->get($id)->change($name, $schedule, $target, $withoutTarget);
        $this->habits->save($habit);

        return $habit;
    }

    /**
     * Archives a habit today: its progress stops there, and it takes no more check-ins.
     *
     * @throws HabitNotFound
     */
    public function archive(string $id): Habit
    {
        $habit = $this->get($id);
        $habit = $habit->archive($this->today($habit));

        $this->habits->save($habit);

        return $habit;
    }

    /**
     * Records what the member did on a day — today when none is given —, in place of what was recorded on that day.
     *
     * @throws HabitNotFound
     * @throws InvalidCheckIn
     */
    public function checkIn(string $id, CheckInStatus $status = CheckInStatus::Done, ?int $value = null, ?Day $day = null): Progress
    {
        $habit = $this->get($id);
        $today = $this->today($habit);
        $day ??= $today;
        $this->assertOpen($habit, $day, $today, ahead: false);

        if (null !== $value && null === $habit->target) {
            throw InvalidCheckIn::because('A habit without a target takes no value.');
        }

        if (CheckInStatus::Partial === $status && null === $habit->target) {
            throw InvalidCheckIn::because('Only a habit with a target is done in part.');
        }

        $before = $this->progressOf($habit, $today);
        $this->checkIns->save(new CheckIn($habit->id, $day, $status, $value, $this->clock->now()));
        $after = $this->progressOf($habit, $today);

        $this->dispatch(new HabitChecked($habit->id, $habit->member, $day->toString()));

        foreach ($this->milestones->streaksBetween($before->streak->current, $after->streak->current) as $length) {
            $this->dispatch(new StreakReached($habit->id, $habit->member, $length, $after->streak->unit));
        }

        foreach ($this->milestones->strengthsBetween($before->strengthPercent(), $after->strengthPercent()) as $percent) {
            $this->dispatch(new StrengthReached($habit->id, $habit->member, $percent));
        }

        return $after;
    }

    /**
     * Freezes days ahead or behind — a trip, an illness —, from a day to another, both included: they neither count
     * nor break a streak. Days already done, in full or in part, stay as they are.
     *
     * @return int the number of days frozen
     *
     * @throws HabitNotFound
     * @throws InvalidCheckIn
     */
    public function freeze(string $id, Day $from, Day $through): int
    {
        $habit = $this->get($id);
        $today = $this->today($habit);

        if ($through->isBefore($from) || $from->daysUntil($through) >= self::MAX_FREEZE_DAYS) {
            throw InvalidCheckIn::because(\sprintf('A freeze runs from a day to the same day or a later one, %d days at most.', self::MAX_FREEZE_DAYS));
        }

        if ($today->daysUntil($through) > self::MAX_FREEZE_DAYS) {
            throw InvalidCheckIn::because(\sprintf('A freeze ends at most %d days ahead.', self::MAX_FREEZE_DAYS));
        }

        $this->assertOpen($habit, $from, $today, ahead: true);
        $now = $this->clock->now();
        $frozen = 0;
        $kept = [];

        foreach ($this->checkIns->between($habit->id, $from, $through) as $checkIn) {
            $kept[$checkIn->day->number] = !$checkIn->status->isExcused();
        }

        for ($day = $from; !$day->isAfter($through); $day = $day->plus(1)) {
            if ($kept[$day->number] ?? false) {
                continue;
            }

            $this->checkIns->save(new CheckIn($habit->id, $day, CheckInStatus::Frozen, null, $now));
            ++$frozen;
        }

        return $frozen;
    }

    /**
     * Takes back what was recorded on a day.
     *
     * @throws HabitNotFound
     * @throws InvalidCheckIn
     */
    public function undo(string $id, Day $day): Progress
    {
        $habit = $this->get($id);
        $today = $this->today($habit);
        $this->assertOpen($habit, $day, $today, ahead: true);

        if ($this->checkIns->get($habit->id, $day) instanceof CheckIn) {
            $this->checkIns->remove($habit->id, $day);
            $this->dispatch(new HabitUnchecked($habit->id, $habit->member, $day->toString()));
        }

        return $this->progressOf($habit, $today);
    }

    /**
     * Where a habit stands today.
     *
     * @throws HabitNotFound
     */
    public function progress(string $id): Progress
    {
        $habit = $this->get($id);

        return $this->progressOf($habit, $this->today($habit));
    }

    /**
     * The check-ins of a habit from a day to another, both included, the earliest first.
     *
     * @return list<CheckIn>
     *
     * @throws HabitNotFound
     */
    public function history(string $id, Day $from, Day $through): array
    {
        return $this->checkIns->between($this->get($id)->id, $from, $through);
    }

    /**
     * The habits of a member, archived ones included.
     *
     * @return list<Habit>
     */
    public function ofMember(string $member): array
    {
        return $this->habits->ofMember($member);
    }

    /**
     * Forgets a habit and its check-ins; nothing happens for a habit not kept.
     */
    public function remove(string $id): void
    {
        $this->checkIns->removeHabit($id);
        $this->habits->remove($id);
    }

    /**
     * Forgets every habit of a member and their check-ins: their erasure.
     *
     * @return int the number of habits forgotten
     */
    public function forgetMember(string $member): int
    {
        $habits = $this->habits->ofMember($member);

        foreach ($habits as $habit) {
            $this->remove($habit->id);
        }

        return \count($habits);
    }

    private function progressOf(Habit $habit, Day $today): Progress
    {
        $last = $habit->archivedOn instanceof Day && $habit->archivedOn->isBefore($today) ? $habit->archivedOn : $today;

        return $this->calculator->calculate($habit, $this->checkIns->between($habit->id, $habit->startsOn, $last), $today);
    }

    /**
     * Refuses an archived habit, a day before its first one or more days back than allowed, and a day ahead of
     * today unless it may be.
     *
     * @throws InvalidCheckIn
     */
    private function assertOpen(Habit $habit, Day $day, Day $today, bool $ahead): void
    {
        if ($habit->archivedOn instanceof Day) {
            throw InvalidCheckIn::because('An archived habit takes no more check-ins.');
        }

        if ($day->isBefore($habit->startsOn)) {
            throw InvalidCheckIn::because('A check-in is on or after the first day of its habit.');
        }

        if ($day->daysUntil($today) > $this->backfillDays) {
            throw InvalidCheckIn::because(\sprintf('A check-in goes back at most %d days.', $this->backfillDays));
        }

        if (!$ahead && $day->isAfter($today)) {
            throw InvalidCheckIn::because('A check-in is on today or a day before.');
        }
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
