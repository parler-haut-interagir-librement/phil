<?php

declare(strict_types=1);

namespace Phil\Assignment\Contract;

use Phil\Assignment\Assignment;
use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Problem;
use Phil\Assignment\Seed;

/**
 * Solves a problem: the same problem and the same seed always give the same assignment.
 */
interface Assigner
{
    /**
     * @throws BudgetExceeded when the work needed passes the assigner's budget
     */
    public function assign(Problem $problem, Seed $seed): Assignment;
}
