<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Closure;
use Throwable;

/**
 * The application's database transactions.
 */
interface Transactions
{
    /**
     * Runs the work in one transaction and commits it; on a throwable, rolls back, forgets the changes held in memory
     * (an ORM's identity map, for instance) and throws it again.
     *
     * @param Closure(): void $work
     *
     * @throws Throwable what the work threw
     */
    public function run(Closure $work): void;
}
