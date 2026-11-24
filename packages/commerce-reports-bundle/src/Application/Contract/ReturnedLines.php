<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Contract;

use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The lines the shop took back. The returns module lists the returns of an order, not those of a period: an
 * application aliases this port to its own service; without one, no return is counted.
 */
interface ReturnedLines
{
    /**
     * The lines returned in the period, its start included and its end excluded, yielded as they are read.
     *
     * @return iterable<ReturnedLine>
     */
    public function between(Period $period): iterable;
}
