<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use Closure;
use Throwable;

/**
 * The transactions of the database that holds the requests, the proofs and the minors. A guardian's answer uses the
 * request up and records a proof for each of its features in one call to `run()`: when a write fails, the request is
 * back and its link answers again.
 */
interface Transactions
{
    /**
     * Runs the work in one transaction and commits it; on a throwable, rolls back, forgets the changes held in memory
     * (an ORM's identity map, for instance) and throws it again.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     *
     * @throws Throwable what the work threw
     */
    public function run(Closure $work): mixed;
}
