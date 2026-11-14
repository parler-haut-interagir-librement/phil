<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use LogicException;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;

/**
 * Creates the database of a tenant placed in one of its own, on the server of the shared connection, with the account
 * of the shared connection, which must be allowed to create databases. A database on another server is created by
 * the operator of that server.
 *
 * The name was checked by {@see Placement::dedicated()}, and is quoted as an identifier besides: it can never be read
 * as SQL.
 */
final readonly class TenantDatabaseCreator
{
    public function __construct(
        private Connection $connection,
        private TenantDatabaseRouter $router,
    ) {
    }

    /**
     * @return bool whether the database was created; `false` when it existed already
     *
     * @throws LogicException when the placement is shared, or the connection is in a tenant's database
     */
    public function create(Placement $placement): bool
    {
        $database = $placement->database ?? throw new LogicException('The shared database is created with the application, not for a tenant.');

        if (!$this->router->current()->isShared()) {
            throw new LogicException(\sprintf('The connection is in the database "%s": create a tenant\'s database in no tenant.', $this->router->current()->toString()));
        }

        foreach ($this->connection->createSchemaManager()->introspectDatabaseNames() as $existing) {
            if ($existing->getIdentifier()->getValue() === $database) {
                return false;
            }
        }

        $this->connection->executeStatement('CREATE DATABASE '.$this->connection->quoteSingleIdentifier($database));

        return true;
    }
}
