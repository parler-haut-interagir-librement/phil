<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Database\DatabaseClient;
use Phil\Backup\Database\MysqlClient;
use Phil\Backup\Database\PostgresqlClient;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Exception\BackupFailed;

/**
 * The client of a Doctrine DBAL connection, chosen by its platform and given its parameters: SQLite, MySQL,
 * MariaDB or PostgreSQL. Another platform, or an in-memory SQLite database, which has no file to copy, is refused.
 * Nothing connects: the platform comes from the parameters or the server version they give.
 */
final class DatabaseClients
{
    public static function forConnection(Connection $connection, CommandRunner $runner): DatabaseClient
    {
        $parameters = self::parameters($connection->getParams());
        $platform = $connection->getDatabasePlatform();
        $database = $parameters['dbname'] ?? '';

        return match (true) {
            $platform instanceof SQLitePlatform => new SqliteClient(self::file($parameters['path'] ?? null), $runner),
            $platform instanceof AbstractMySQLPlatform => new MysqlClient($database, $parameters, $runner, $platform instanceof MariaDBPlatform),
            $platform instanceof PostgreSQLPlatform => new PostgresqlClient($database, $parameters, $runner),
            default => throw BackupFailed::because(\sprintf('The bundle backs up and restores SQLite, MySQL, MariaDB and PostgreSQL databases, not one on %s.', $platform::class)),
        };
    }

    /**
     * The connection parameters the clients read, as strings.
     *
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, string>
     */
    private static function parameters(array $parameters): array
    {
        $strings = [];

        foreach (['dbname', 'path', 'host', 'port', 'user', 'password', 'unix_socket'] as $name) {
            $value = $parameters[$name] ?? null;

            if (\is_string($value) || \is_int($value)) {
                $strings[$name] = (string) $value;
            }
        }

        return $strings;
    }

    private static function file(?string $path): string
    {
        if (null === $path || '' === $path || ':memory:' === $path) {
            throw BackupFailed::because('An in-memory SQLite database has no file to back up or restore.');
        }

        return $path;
    }
}
