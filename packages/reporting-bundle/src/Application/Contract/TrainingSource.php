<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training activities of a learning system, which the training reports total by period and by organisational unit.
 * An application aliases it to its own service; without one, the training reports are not registered.
 */
interface TrainingSource
{
    /**
     * The ISO 4217 code of the currency every cost is counted in: `CAD`.
     */
    public function currency(): string;

    /**
     * The activities that count on a day of the period. Yield them from a query iterated row by row: the reports hold
     * their totals in memory, never the activities. An activity outside the period is left out.
     *
     * @return iterable<TrainingActivity>
     */
    public function activities(Period $period): iterable;
}
