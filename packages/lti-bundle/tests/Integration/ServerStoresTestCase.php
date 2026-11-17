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
 * The contract of the stores on a real server, read from the environment; skipped without it. `phil-dev services up`
 * starts MySQL 8.4 and PostgreSQL 17 in Docker, and `phil-dev services env` gives their addresses.
 */
abstract class ServerStoresTestCase extends StoresTestCase
{
    private ?Connection $connection = null;

    /**
     * The variables of the server's URL: the one of this package, then the one `phil-dev services` sets.
     *
     * @return array{string, string}
     */
    abstract protected function variables(): array;

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

        [$variable, $shared] = $this->variables();
        $url = getenv($variable);
        $url = \is_string($url) && '' !== $url ? $url : getenv($shared);

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s, or %s, to run this test against a real server.', $variable, $shared));
        }

        return $this->connection = Stores::prepare(DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url), Stores::configuration($this->interleaving)), $this->tables);
    }
}
