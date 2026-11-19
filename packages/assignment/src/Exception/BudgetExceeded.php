<?php

declare(strict_types=1);

namespace Phil\Assignment\Exception;

use RuntimeException;

/**
 * The assignment needed more work than its budget allows: the problem is too large for one run.
 */
final class BudgetExceeded extends RuntimeException implements AssignmentException
{
    public static function after(int $operations): self
    {
        return new self(\sprintf('The assignment stopped after %d operations, the limit of its work budget.', $operations));
    }
}
