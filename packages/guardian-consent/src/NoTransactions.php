<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Closure;
use Override;
use Phil\GuardianConsent\Contract\Transactions;

/**
 * Runs the work as it comes, without a transaction: for the stores in memory, and for stores that are not in one
 * database. A write that fails leaves what was written before it.
 */
final readonly class NoTransactions implements Transactions
{
    #[Override]
    public function run(Closure $work): mixed
    {
        return $work();
    }
}
