<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;

/**
 * Runs the work as it comes and writes down where each transaction began and was committed, for the tests.
 */
final class RecordingTransactions implements Transactions
{
    /** @var list<string> */
    public array $runs = [];

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
        $this->runs[] = 'begin';
        $result = $work();
        $this->runs[] = 'commit';

        return $result;
    }
}
