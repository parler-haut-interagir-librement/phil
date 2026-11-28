<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application;

use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Domain\Repository\AbsenceRepository;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Psr\Clock\ClockInterface;

/**
 * The unannounced absences: a coordinator records that a volunteer who held a place did not come once the shift
 * started, or takes back an absence recorded by mistake. The absences recorded over the configured window and not
 * lifted are counted; past the configured threshold, the volunteer no longer joins a shift by themselves — by signing
 * up, by interest or by the swap board — until an organiser lifts the block. A coordinator may still assign them.
 * Without a threshold, the absences are counted and block nothing.
 */
final readonly class Absences
{
    public const int WINDOW_DAYS = 365;

    public function __construct(
        private AbsenceRepository $absences,
        private RosterRepository $rosters,
        private ClockInterface $clock,
        private ?int $threshold = null,
        private int $windowDays = self::WINDOW_DAYS,
    ) {
    }

    /**
     * Records the absence of the volunteer on the shift; false when it was recorded already.
     *
     * @throws RequestRefused with `shifts.unknown_shift` or `shifts.not_started`
     * @throws ShiftRefused   with `shift.not_on_roster` when the volunteer held no place on it
     */
    public function record(string $shiftId, string $volunteer, string $coordinator): bool
    {
        $roster = ($this->rosters->find($shiftId) ?? throw new RequestRefused(RequestRefused::UNKNOWN_SHIFT))->roster;
        if (!$this->hasStarted($roster)) {
            throw new RequestRefused(RequestRefused::NOT_STARTED);
        }

        if (PlaceStatus::Confirmed !== $roster->statusOf($volunteer)) {
            throw ShiftRefused::because(ShiftRefused::NOT_ON_ROSTER);
        }

        return $this->absences->add(new Absence($shiftId, $volunteer, $this->clock->now(), $coordinator));
    }

    /**
     * Whether the shift started, by the server's clock, so that an absence on it may be recorded.
     *
     * @phpstan-impure
     */
    public function hasStarted(Roster $roster): bool
    {
        return $this->clock->now() >= $roster->shift->startsAt;
    }

    /**
     * Takes back the absence of the volunteer on the shift, recorded by mistake; false when there was none.
     */
    public function forgive(string $shiftId, string $volunteer): bool
    {
        return $this->absences->remove($shiftId, $volunteer);
    }

    /**
     * The absences of the volunteer that count: recorded over the window and not lifted.
     *
     * @phpstan-impure
     */
    public function count(string $volunteer): int
    {
        return $this->absences->countSince($volunteer, $this->clock->now()->modify(\sprintf('-%d days', $this->windowDays)));
    }

    /**
     * Whether the volunteer reached the threshold, and no longer joins a shift by themselves.
     *
     * @phpstan-impure
     */
    public function isBlocked(string $volunteer): bool
    {
        return null !== $this->threshold && $this->count($volunteer) >= $this->threshold;
    }

    /**
     * @throws RequestRefused with `shifts.absences` when the volunteer reached the threshold
     */
    public function assertMayJoinFreely(string $volunteer): void
    {
        if ($this->isBlocked($volunteer)) {
            throw new RequestRefused(RequestRefused::ABSENCES);
        }
    }

    /**
     * An organiser lifts the block: the absences that counted no longer do. The number of absences lifted.
     */
    public function lift(string $volunteer, string $organiser): int
    {
        return $this->absences->lift($volunteer, $organiser, $this->clock->now());
    }

    /**
     * @return list<Absence> the absences of the volunteer, the latest first, lifted ones too
     *
     * @phpstan-impure
     */
    public function ofVolunteer(string $volunteer): array
    {
        return $this->absences->ofVolunteer($volunteer);
    }

    /**
     * @return list<string> the volunteers recorded absent on the shift
     *
     * @phpstan-impure
     */
    public function absentFrom(string $shiftId): array
    {
        return $this->absences->absentFrom($shiftId);
    }
}
