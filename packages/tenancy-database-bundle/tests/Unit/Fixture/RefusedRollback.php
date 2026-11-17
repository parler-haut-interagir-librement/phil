<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Override;

/**
 * A connection whose server is gone when a transaction is rolled back: each rollback fails.
 */
final class RefusedRollback extends Connection
{
    public int $attempts = 0;

    #[Override]
    public function rollBack(): void
    {
        ++$this->attempts;

        throw new ConnectionException('The server has gone away.');
    }
}
