<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Contract;

use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The lines the shop sold: `PosSoldLines` reads the sales of the point of sale; an application aliases this port to
 * its own service to count its other channels.
 */
interface SoldLines
{
    /**
     * The lines sold in the period, its start included and its end excluded. Yield them as they are read: the reports
     * hold their totals in memory, never the lines.
     *
     * @return iterable<SoldLine>
     */
    public function between(Period $period): iterable;
}
