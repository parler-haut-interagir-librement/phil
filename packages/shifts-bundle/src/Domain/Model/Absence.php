<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Model;

use DateTimeImmutable;

/**
 * A volunteer who held a place on a shift and did not come without saying so, as a coordinator recorded it: the shift,
 * the instant and the coordinator. Once an organiser lifts the block it caused, the absence keeps the instant and the
 * person of the lift and no longer counts.
 */
final class Absence
{
    public function __construct(
        public readonly string $shiftId,
        public readonly string $volunteer,
        public readonly DateTimeImmutable $recordedAt,
        public readonly string $recordedBy,
        public readonly ?DateTimeImmutable $liftedAt = null,
        public readonly ?string $liftedBy = null,
    ) {
    }

    public function isLifted(): bool
    {
        return $this->liftedAt instanceof DateTimeImmutable;
    }
}
