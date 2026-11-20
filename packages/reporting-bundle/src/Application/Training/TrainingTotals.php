<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use Phil\ReportingBundle\Domain\Training\UnitNode;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training of a period, for an organisational unit or for every unit: the number of activities, their hours in
 * hundredths, their cost and their eligible cost in minor units.
 */
final readonly class TrainingTotals
{
    /**
     * @param list<UnitNode> $path the unit's path from its root, the unit last; empty for the activities without a unit, or for every unit
     */
    public function __construct(
        public Period $period,
        public array $path,
        public int $activities,
        public int $hundredthsOfHours,
        public int $cost,
        public int $eligibleCost,
    ) {
    }

    /**
     * The unit the totals are of, or null.
     */
    public function unit(): ?UnitNode
    {
        return array_last($this->path);
    }
}
