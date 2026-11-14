<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * A driver whose connections are handed to the {@see TenantSession} as they open.
 */
final class TenantSessionDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly TenantSession $session,
    ) {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $connection = parent::connect($params);
        $this->session->attach($connection);

        return $connection;
    }
}
