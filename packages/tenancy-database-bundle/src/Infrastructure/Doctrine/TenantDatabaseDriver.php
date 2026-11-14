<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * A driver that opens each connection on the database the router names.
 */
final class TenantDatabaseDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly TenantDatabaseRouter $router,
    ) {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        return parent::connect($this->router->parameters($params));
    }
}
