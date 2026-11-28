<?php

declare(strict_types=1);

namespace Phil\Shifts\Roster;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Shifts\Shift\Identifier;

/**
 * A volunteer on a roster: their status, the instant they took it — which orders the reserve and the interested —, and
 * the coordinator who assigned the place, when one did.
 */
final readonly class Place
{
    public DateTimeImmutable $at;

    public function __construct(
        public string $volunteer,
        public PlaceStatus $status,
        DateTimeImmutable $at,
        public ?string $assignedBy = null,
    ) {
        Identifier::volunteer($volunteer);

        if (null !== $assignedBy) {
            Identifier::volunteer($assignedBy);
        }

        $this->at = $at->setTimezone(new DateTimeZone('UTC'));
    }

    public function with(PlaceStatus $status, DateTimeImmutable $at, ?string $assignedBy = null): self
    {
        return new self($this->volunteer, $status, $at, $assignedBy);
    }
}
