<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Override;

/**
 * The DBAL middleware of the connection that follows the active tenant: each time it opens, the
 * {@see TenantDatabaseRouter} decides on which database.
 */
final readonly class TenantDatabaseMiddleware implements Middleware
{
    public function __construct(
        private TenantDatabaseRouter $router,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new TenantDatabaseDriver($driver, $this->router);
    }
}
