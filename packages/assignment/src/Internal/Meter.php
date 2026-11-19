<?php

declare(strict_types=1);

namespace Phil\Assignment\Internal;

use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\WorkBudget;

/**
 * The work one assignment has done, against its budget; made for each run, never shared.
 *
 * @internal
 */
final class Meter
{
    private int $spent = 0;

    public function __construct(
        private readonly WorkBudget $budget,
    ) {
    }

    /**
     * @throws BudgetExceeded
     */
    public function spend(int $operations = 1): void
    {
        $this->spent += $operations;

        if ($this->spent > $this->budget->operations) {
            throw BudgetExceeded::after($this->budget->operations);
        }
    }
}
