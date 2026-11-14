<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;

/**
 * Which database a Doctrine DBAL connection speaks to, read from its parameters for the production guard: `sqlite`
 * and its file (`:memory:` for an in-memory database), or `mariadb`, `mysql`, `postgresql` — the platform's class
 * for another — with the host and the name. Nothing connects: the platform comes from the parameters or the server
 * version they give.
 */
final class ConnectionIdentity
{
    public static function of(Connection $connection): DatabaseIdentity
    {
        $parameters = $connection->getParams();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            $path = $parameters['path'] ?? null;

            return new DatabaseIdentity('sqlite', null, \is_string($path) && '' !== $path ? $path : ':memory:');
        }

        $host = $parameters['host'] ?? null;
        $name = $parameters['dbname'] ?? null;

        return new DatabaseIdentity(self::platform($platform), \is_string($host) ? $host : null, \is_string($name) ? $name : '');
    }

    private static function platform(AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof MariaDBPlatform => 'mariadb',
            $platform instanceof AbstractMySQLPlatform => 'mysql',
            $platform instanceof PostgreSQLPlatform => 'postgresql',
            default => $platform::class,
        };
    }
}
