<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\DBAL\Connection;
use Override;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;

/**
 * The writes of a loan in one transaction of the library's connection. The entity manager's flush and the stockroom's
 * statements on the same connection nest in it, so that the stock moves with the loan or not at all.
 */
final readonly class DbalLoanTransaction implements LoanTransaction
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function run(Closure $work): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $work());
    }
}
