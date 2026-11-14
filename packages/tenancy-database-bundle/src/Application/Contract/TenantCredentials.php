<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Application\Contract;

use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;

/**
 * The account a dedicated database is reached with, when it is not the account of the shared connection.
 */
interface TenantCredentials
{
    /**
     * Asked each time the connection opens on the database. `null` keeps the user, the password, the host and the
     * port of the shared connection, with only the database name replaced.
     */
    public function credentialsFor(string $database): ?DatabaseCredentials;
}
