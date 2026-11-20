<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The payroll of a period, which the template of the 1 % of payroll compares the eligible training costs with.
 */
interface PayrollSource
{
    /**
     * The total payroll of the period, in minor units of the training source's currency; null when it is not known.
     */
    public function payroll(Period $period): ?int;
}
