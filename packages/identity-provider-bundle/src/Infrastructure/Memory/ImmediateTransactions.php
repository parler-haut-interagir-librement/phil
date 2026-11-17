<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;

/**
 * The work run as it comes, for the stores in memory, which have nothing to undo.
 */
final readonly class ImmediateTransactions implements Transactions
{
    /**
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    #[Override]
    public function run(Closure $work): mixed
    {
        return $work();
    }
}
