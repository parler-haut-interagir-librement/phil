<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Contract;

use Closure;

/**
 * Runs the writes of a loan in one transaction: the move of its stockroom item and the loan itself, kept together or
 * not at all. Without one, each is written on its own, the stock first.
 */
interface LoanTransaction
{
    /**
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T what the work returns, once it is committed
     */
    public function run(Closure $work): mixed;
}
