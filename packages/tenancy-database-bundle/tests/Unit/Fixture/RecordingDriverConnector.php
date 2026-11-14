<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * The driver of {@see RecordingDriver}: hands the parameters of each connection to it.
 */
final class RecordingDriverConnector extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly RecordingDriver $recorder,
    ) {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        return $this->recorder->record($params);
    }
}
