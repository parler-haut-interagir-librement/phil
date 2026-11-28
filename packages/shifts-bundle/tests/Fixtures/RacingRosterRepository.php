<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use DateTimeImmutable;
use Override;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Domain\Model\StoredRoster;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;

/**
 * A repository whose first writes lose to another request, as if one wrote between each read and write.
 */
final class RacingRosterRepository implements RosterRepository
{
    public int $reads = 0;

    public ?int $limit = null;

    public function __construct(
        private readonly RosterRepository $inner,
        private int $losses,
    ) {
    }

    #[Override]
    public function add(Roster $roster): bool
    {
        return $this->inner->add($roster);
    }

    #[Override]
    public function update(Roster $roster, int $revision): bool
    {
        if ($this->losses > 0) {
            --$this->losses;

            return false;
        }

        return $this->inner->update($roster, $revision);
    }

    #[Override]
    public function find(string $shiftId): ?StoredRoster
    {
        ++$this->reads;

        return $this->inner->find($shiftId);
    }

    #[Override]
    public function remove(string $shiftId): void
    {
        $this->inner->remove($shiftId);
    }

    #[Override]
    public function upcoming(DateTimeImmutable $after, int $limit = 200): array
    {
        $this->limit = $limit;

        return $this->inner->upcoming($after, $limit);
    }

    #[Override]
    public function ofVolunteer(string $volunteer): array
    {
        return $this->inner->ofVolunteer($volunteer);
    }

    #[Override]
    public function eraseVolunteer(string $volunteer): array
    {
        return $this->inner->eraseVolunteer($volunteer);
    }
}
