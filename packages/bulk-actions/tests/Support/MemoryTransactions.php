<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Closure;
use Phil\BulkActions\Contract\Transactions;
use Throwable;

/**
 * Transactions over the products: a commit flushes them, a rollback forgets what was changed in memory.
 */
final class MemoryTransactions implements Transactions
{
    public int $commits = 0;

    public int $rollbacks = 0;

    public function __construct(private readonly MemoryProducts $products)
    {
    }

    public function run(Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $throwable) {
            ++$this->rollbacks;
            $this->products->clear();

            throw $throwable;
        }

        $this->products->flush();
        ++$this->commits;
    }
}
