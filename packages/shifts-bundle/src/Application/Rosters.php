<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application;

use Closure;
use Phil\Shifts\Allocation\InterestAllocator;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The shifts and their rosters: planned and corrected by a coordinator, joined and left by volunteers, assigned and
 * allocated. A volunteer never holds two fixed shifts that overlap, with the rest the configuration asks between them.
 * The clock is the server's; a write is made over the revision read, and done again from a fresh read when another
 * request came first. Once stored, the events of the change are dispatched, when the application has a dispatcher.
 *
 * With `Minors`, a volunteer who is a minor on the shift's day joins it only with the consent of a guardian recorded;
 * with `Absences`, a volunteer past the threshold of unannounced absences no longer joins a shift by themselves.
 */
final readonly class Rosters
{
    private const int WRITES = 3;

    public function __construct(
        private RosterRepository $rosters,
        private ClockInterface $clock,
        private Conflicts $conflicts,
        private InterestAllocator $allocator,
        private ?EventDispatcherInterface $events = null,
        private ?Minors $minors = null,
        private ?Absences $absences = null,
    ) {
    }

    /**
     * @throws RequestRefused when a shift has the identifier already
     */
    public function plan(Shift $shift): Roster
    {
        $roster = Roster::open($shift);

        if (!$this->rosters->add($roster)) {
            throw new RequestRefused(RequestRefused::EXISTS);
        }

        return $roster;
    }

    /**
     * A shift corrected by a coordinator, with the same volunteers.
     *
     * @throws RequestRefused when the shift is unknown, or the roster changed three times meanwhile
     */
    public function correct(Shift $shift): Roster
    {
        return $this->change($shift->id, static fn (Roster $roster): Roster => $roster->withShift($shift));
    }

    public function remove(string $shiftId): void
    {
        $this->rosters->remove($shiftId);
    }

    /**
     * A volunteer takes a place, or waits in the reserve. A volunteer who holds a fixed shift that overlaps is
     * refused with `shift.conflict`.
     *
     * @param list<string> $roles
     *
     * @throws ShiftRefused   when the roster refuses
     * @throws RequestRefused when the shift is unknown or busy, when the volunteer is a minor without the consent of a
     *                        guardian, or past the threshold of absences
     */
    public function signUp(string $shiftId, string $volunteer, array $roles): Roster
    {
        return $this->change($shiftId, fn (Roster $roster): Roster => $this->free($this->admit($roster, $volunteer, true), $volunteer)->signUp($volunteer, $roles, $this->clock->now()));
    }

    /**
     * A volunteer says they are interested. A volunteer who holds a fixed shift that overlaps is refused with
     * `shift.conflict`.
     *
     * @param list<string> $roles
     *
     * @throws ShiftRefused   when the roster refuses
     * @throws RequestRefused when the shift is unknown or busy, when the volunteer is a minor without the consent of a
     *                        guardian, or past the threshold of absences
     */
    public function expressInterest(string $shiftId, string $volunteer, array $roles): Roster
    {
        return $this->change($shiftId, fn (Roster $roster): Roster => $this->free($this->admit($roster, $volunteer, true), $volunteer)->expressInterest($volunteer, $roles, $this->clock->now()));
    }

    /**
     * A coordinator gives a place. A volunteer who holds a fixed shift that overlaps is refused
     * with `shift.conflict`; the threshold of absences does not count.
     *
     * @throws ShiftRefused   when the roster refuses
     * @throws RequestRefused when the shift is unknown or busy, or the volunteer is a minor without the consent of a
     *                        guardian
     */
    public function assign(string $shiftId, string $volunteer, string $coordinator): Roster
    {
        return $this->change($shiftId, fn (Roster $roster): Roster => $this->free($this->admit($roster, $volunteer, false), $volunteer)->assign($volunteer, $coordinator, $this->clock->now()));
    }

    /**
     * A volunteer leaves the shift, or a coordinator takes them off it.
     *
     * @throws ShiftRefused   when the roster refuses
     * @throws RequestRefused when the shift is unknown or busy
     */
    public function withdraw(string $shiftId, string $volunteer): Roster
    {
        return $this->change($shiftId, fn (Roster $roster): Roster => $roster->withdraw($volunteer, $this->clock->now()));
    }

    /**
     * The free places allocated among the interested, balanced by the minutes each one holds on the other shifts to
     * come; an interested volunteer whom a place would put in a conflict, a minor without the consent of a guardian
     * and a volunteer past the threshold of absences are left out.
     *
     * @throws ShiftRefused   when the roster refuses
     * @throws RequestRefused when the shift is unknown or busy
     */
    public function allocate(string $shiftId): Roster
    {
        return $this->change($shiftId, function (Roster $roster): Roster {
            $now = $this->clock->now();
            $held = ServedHours::held($this->rosters->upcoming($now, 500));
            $eligible = [];

            foreach ($roster->interested() as $place) {
                if ($this->admissible($roster, $place->volunteer, true) && !$this->conflicting($roster, $place->volunteer)) {
                    $eligible[] = $place;
                }
            }

            $candidates = new Roster($roster->shift, [...$roster->confirmed(), ...$roster->reserve(), ...$eligible]);
            $allocated = $candidates->allocate($this->allocator, $held, $now);
            $left = array_filter($roster->interested(), static fn (Place $place): bool => !\in_array($place, $eligible, true));

            return new Roster($roster->shift, [...$allocated->places, ...$left], $allocated->changes);
        });
    }

    /**
     * @phpstan-impure
     */
    public function find(string $shiftId): ?Roster
    {
        return $this->rosters->find($shiftId)?->roster;
    }

    /**
     * @return list<Roster> the shifts to come, in the order they start
     *
     * @phpstan-impure
     */
    public function upcoming(int $limit = 200): array
    {
        return $this->rosters->upcoming($this->clock->now(), $limit);
    }

    /**
     * @return list<Roster> the shifts on which the volunteer stands
     *
     * @phpstan-impure
     */
    public function ofVolunteer(string $volunteer): array
    {
        return $this->rosters->ofVolunteer($volunteer);
    }

    /**
     * Whether volunteers may still join or leave the shift, by the server's clock.
     *
     * @phpstan-impure
     */
    public function isOpen(Roster $roster): bool
    {
        return $roster->shift->isOpenAt($this->clock->now());
    }

    /**
     * @phpstan-impure
     */
    public function served(string $volunteer): ServedHours
    {
        return ServedHours::of($volunteer, $this->rosters->ofVolunteer($volunteer), $this->clock->now());
    }

    /**
     * Reads the roster, applies the change and writes it over the revision read, three times at most.
     *
     * @param Closure(Roster): Roster $change
     *
     * @param-immediately-invoked-callable $change
     *
     * @throws RequestRefused when the shift is unknown, or another request wrote each time first
     */
    private function change(string $shiftId, Closure $change): Roster
    {
        for ($write = 0; $write < self::WRITES; ++$write) {
            $stored = $this->rosters->find($shiftId) ?? throw new RequestRefused(RequestRefused::UNKNOWN_SHIFT);
            $next = $change($stored->roster);

            if ($this->rosters->update($next, $stored->revision)) {
                $this->dispatch($next);

                return $next;
            }
        }

        throw new RequestRefused(RequestRefused::BUSY);
    }

    /**
     * Dispatches the events of the change that returned the roster.
     */
    public function dispatch(Roster $roster): void
    {
        foreach ($roster->changes as $event) {
            $this->events?->dispatch($event);
        }
    }

    /**
     * Whether the volunteer may join the shift by the swap board: a minor needs the consent of a guardian, and a
     * volunteer past the threshold of absences is refused.
     *
     * @throws RequestRefused with `shifts.guardian_consent` or `shifts.absences`
     */
    public function assertMayTake(Roster $roster, string $volunteer): void
    {
        $this->admit($roster, $volunteer, true);
    }

    /**
     * The roster, once the volunteer may join it: of age or with a guardian's consent, and, when they join by
     * themselves, below the threshold of absences.
     *
     * @throws RequestRefused when they may not
     */
    private function admit(Roster $roster, string $volunteer, bool $freely): Roster
    {
        if ($freely) {
            $this->absences?->assertMayJoinFreely($volunteer);
        }

        $this->minors?->assertMayJoin($volunteer, $roster->shift);

        return $roster;
    }

    private function admissible(Roster $roster, string $volunteer, bool $freely): bool
    {
        return (!$freely || true !== $this->absences?->isBlocked($volunteer)) && false !== $this->minors?->mayJoin($volunteer, $roster->shift);
    }

    /**
     * The roster, once the volunteer is known to hold no fixed shift it overlaps.
     *
     * @throws ShiftRefused when they hold one
     */
    private function free(Roster $roster, string $volunteer): Roster
    {
        if ($this->conflicting($roster, $volunteer)) {
            throw ShiftRefused::because(ShiftRefused::CONFLICT);
        }

        return $roster;
    }

    private function conflicting(Roster $roster, string $volunteer): bool
    {
        $held = [];

        foreach ($this->rosters->ofVolunteer($volunteer) as $other) {
            if (PlaceStatus::Confirmed === $other->statusOf($volunteer)) {
                $held[] = $other->shift;
            }
        }

        return [] !== $this->conflicts->of($roster->shift, $held);
    }
}
