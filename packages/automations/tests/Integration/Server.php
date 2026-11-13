<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;

/**
 * A connection to the server whose URL a variable holds; the test is skipped without it.
 */
final class Server
{
    public static function connect(string $variable): Connection
    {
        $url = getenv($variable);

        if (!\is_string($url) || '' === $url) {
            TestCase::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $variable));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    /**
     * Drops the table, then creates it as a store configures it.
     *
     * @param callable(Schema): Schema $configure
     */
    public static function reset(Connection $connection, string $table, callable $configure): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($configure($manager->introspectSchema()));
    }
}
