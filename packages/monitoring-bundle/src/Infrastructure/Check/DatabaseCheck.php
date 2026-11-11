<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Check;

use Doctrine\DBAL\Connection;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * A DBAL connection answers the platform's cheapest query, `SELECT 1` or its equivalent. A connection that cannot be
 * opened throws, and the check counts as down.
 */
final readonly class DatabaseCheck implements HealthCheck
{
    public function __construct(
        private Connection $connection,
        private string $name = 'database',
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $this->connection->executeQuery($this->connection->getDatabasePlatform()->getDummySelectSQL())->free();

        return CheckOutcome::up('Answers.');
    }
}
