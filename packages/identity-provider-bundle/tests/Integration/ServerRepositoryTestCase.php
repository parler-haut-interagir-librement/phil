<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\IdentityProviderBundle\Tests\Unit\Infrastructure\DoctrineRepositoryTestCase;

/**
 * The Doctrine stores on a real server read from the environment, their tables dropped and created from the mapping.
 */
abstract class ServerRepositoryTestCase extends DoctrineRepositoryTestCase
{
    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function connection(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
