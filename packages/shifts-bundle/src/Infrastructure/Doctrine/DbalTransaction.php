<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ShiftsBundle\Application\Contract\Transaction;

/**
 * A transaction of the bundle's connection: committed when the work returns, rolled back when it throws.
 */
final readonly class DbalTransaction implements Transaction
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function run(callable $work): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $work());
    }
}
