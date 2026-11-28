<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\ShiftsBundle\Domain\Model\Absence;

/**
 * The unannounced absences, one per volunteer and shift.
 */
interface AbsenceRepository
{
    /**
     * Adds the absence; false when the volunteer has one on that shift already.
     */
    public function add(Absence $absence): bool;

    /**
     * Deletes the absence of the volunteer on the shift; false when there was none.
     */
    public function remove(string $shiftId, string $volunteer): bool;

    /**
     * The absences of the volunteer not lifted, recorded at the instant given or after.
     *
     * @phpstan-impure
     */
    public function countSince(string $volunteer, DateTimeImmutable $since): int;

    /**
     * Lifts the absences of the volunteer not lifted yet; the number lifted.
     */
    public function lift(string $volunteer, string $liftedBy, DateTimeImmutable $at): int;

    /**
     * The absences of the volunteer, the latest first, 500 at most.
     *
     * @return list<Absence>
     *
     * @phpstan-impure
     */
    public function ofVolunteer(string $volunteer): array;

    /**
     * The volunteers recorded absent on the shift.
     *
     * @return list<string>
     *
     * @phpstan-impure
     */
    public function absentFrom(string $shiftId): array;

    /**
     * Deletes the absences of the volunteer.
     */
    public function eraseVolunteer(string $volunteer): void;
}
