<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Override;

/**
 * The DBAL middleware of the connections row-level security protects: each connection they open is handed to the
 * {@see TenantSession}, which sets the active tenant on it.
 */
final readonly class TenantSessionMiddleware implements Middleware
{
    public function __construct(
        private TenantSession $session,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new TenantSessionDriver($driver, $this->session);
    }
}
