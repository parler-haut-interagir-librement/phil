<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Closure;
use Throwable;

/**
 * Runs work that writes to several stores as one: all of it is kept, or none when it throws. Closing a session and
 * revoking the tokens issued in it go together this way.
 */
interface Transactions
{
    /**
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     *
     * @throws Throwable what the work throws, once its writes are undone
     */
    public function run(Closure $work): mixed;
}
