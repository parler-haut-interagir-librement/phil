<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;

/**
 * The most work an assignment may do, counted in elementary steps — a choice tried, an edge relaxed —, so that a
 * problem within the limits but hostile in shape still stops: past it, the assigner throws `BudgetExceeded` instead of
 * holding a worker. The default lets a thousand people and a hundred slots through several times over.
 */
final readonly class WorkBudget
{
    /**
     * @throws InvalidProblem
     */
    public function __construct(
        public int $operations = 50_000_000,
    ) {
        if ($operations < 1) {
            throw InvalidProblem::because('A work budget is at least one operation.');
        }
    }
}
