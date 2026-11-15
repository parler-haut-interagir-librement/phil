<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\LtiBundle\Tests\Unit\Fixture\Stores;
use Phil\LtiBundle\Tests\Unit\Infrastructure\StoresTestCase;

/**
 * The contract of the stores on a real server, read from the environment; skipped without it.
 */
abstract class ServerStoresTestCase extends StoresTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    #[Override]
    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $this->connection = Stores::prepare(DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url)), $this->tables);
    }
}
