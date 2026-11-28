<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Domain\Model\StoredRoster;

/**
 * The shifts and their rosters. A write is conditional on the revision read: of two requests that change the same
 * roster at once, the second is told and reads again.
 */
interface RosterRepository
{
    /**
     * Adds a shift with its roster; false when a shift has its identifier already.
     */
    public function add(Roster $roster): bool;

    /**
     * Writes the roster over the revision read; false when another write came first.
     */
    public function update(Roster $roster, int $revision): bool;

    /**
     * @phpstan-impure
     */
    public function find(string $shiftId): ?StoredRoster;

    /**
     * Removes a shift, its roster and the offers of its swap board.
     */
    public function remove(string $shiftId): void;

    /**
     * The shifts whose window ends after the instant, in the order they start, 500 at most.
     *
     * @return list<Roster>
     *
     * @phpstan-impure
     */
    public function upcoming(DateTimeImmutable $after, int $limit = 200): array;

    /**
     * The shifts on which the volunteer stands, held, in reserve or interested, in the order they start.
     *
     * @return list<Roster>
     *
     * @phpstan-impure
     */
    public function ofVolunteer(string $volunteer): array;

    /**
     * Takes the volunteer off every roster, and returns the shifts they stood on.
     *
     * @return list<string>
     */
    public function eraseVolunteer(string $volunteer): array;
}
