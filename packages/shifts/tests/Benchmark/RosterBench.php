<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Format\RosterFormat;
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

/**
 * The hot paths of a roster at its bounds — 1,000 places, a reserve of 100 and 1,000 interested —: a sign-up into the
 * reserve, a withdrawal that promotes, an allocation of 500 places among 1,000 interested, reading the roster back from
 * its storage, and the minutes held over 200 rosters.
 */
final readonly class RosterBench
{
    private Roster $full;

    private Roster $interest;

    /** @var list<array<string, string>> */
    private array $stored;

    /** @var list<Roster> */
    private array $rosters;

    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable('2026-11-28T07:00:00Z');
        $starts = new DateTimeImmutable('2026-11-28T09:00:00Z');
        $ends = new DateTimeImmutable('2026-11-28T12:00:00Z');
        $shift = new Shift('big', 'Big', ShiftKind::Fixed, $starts, $ends, null, 1000, AssignmentMode::SelfSignUp, 100);
        $places = array_map(fn (int $i): Place => new Place('v'.$i, PlaceStatus::Confirmed, $this->now), range(1, 1000));
        $reserve = array_map(fn (int $i): Place => new Place('r'.$i, PlaceStatus::Reserve, $this->now), range(1, 99));
        $this->full = new Roster($shift, [...$places, ...$reserve]);
        $this->stored = RosterFormat::toArray($this->full);

        $interestShift = new Shift('pick', 'Pick', ShiftKind::Duration, $starts, $ends, 60, 500, AssignmentMode::Interest);
        $this->interest = new Roster($interestShift, array_map(fn (int $i): Place => new Place('i'.$i, PlaceStatus::Interested, $this->now->modify('+'.$i.' seconds')), range(1, 1000)));
        $this->rosters = array_map(static fn (int $i): Roster => new Roster(new Shift('s'.$i, 'S', ShiftKind::Fixed, $starts, $ends, null, 10), array_map(static fn (int $v): Place => new Place('v'.(($i + $v) % 300), PlaceStatus::Confirmed, $starts), range(1, 10))), range(1, 200));
    }

    public function benchSignUpIntoTheReserve(): void
    {
        $this->full->signUp('newcomer', [], $this->now);
    }

    public function benchWithdrawalThatPromotes(): void
    {
        $this->full->withdraw('v500', $this->now);
    }

    public function benchAllocation(): void
    {
        $this->interest->allocate(new FairAllocator(), ServedHours::held($this->rosters), $this->now);
    }

    public function benchReadStoredRoster(): void
    {
        RosterFormat::fromArray($this->full->shift, $this->stored);
    }

    /**
     * @return array<string, int>
     */
    public function benchMinutesHeld(): array
    {
        return ServedHours::held($this->rosters);
    }
}
