<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Phil\Backup\Database\MysqlClient;
use Phil\Backup\Database\PostgresqlClient;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Exception\BackupFailed;
use Phil\BackupBundle\Infrastructure\Doctrine\DatabaseClients;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The client of each platform, given the connection's parameters, without connecting.
 */
#[CoversClass(DatabaseClients::class)]
final class DatabaseClientsTest extends TestCase
{
    public function testEachPlatformHasItsClient(): void
    {
        $runner = new ProcessCommandRunner();

        foreach ([
            'mysql database shop on db.test' => [MysqlClient::class, ['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0', 'host' => 'db.test', 'port' => 3307, 'user' => 'app', 'password' => 'secret', 'dbname' => 'shop', 'charset' => 'utf8mb4']],
            'mariadb database shop' => [MysqlClient::class, ['driver' => 'pdo_mysql', 'serverVersion' => '11.4.2-MariaDB', 'unix_socket' => '/run/mysqld.sock', 'dbname' => 'shop']],
            'postgresql database shop on db.test' => [PostgresqlClient::class, ['driver' => 'pdo_pgsql', 'serverVersion' => '17.0', 'host' => 'db.test', 'dbname' => 'shop']],
            'postgresql database ' => [PostgresqlClient::class, ['driver' => 'pdo_pgsql', 'serverVersion' => '17.0']],
            'sqlite database /data/app.sqlite' => [SqliteClient::class, ['driver' => 'pdo_sqlite', 'path' => '/data/app.sqlite']],
        ] as $description => [$class, $parameters]) {
            $client = DatabaseClients::forConnection(DriverManager::getConnection($parameters), $runner);
            self::assertInstanceOf($class, $client);
            self::assertSame($description, $client->identity()->describe());
        }
    }

    public function testTheParametersReachTheClient(): void
    {
        $client = DatabaseClients::forConnection(DriverManager::getConnection(['driver' => 'pdo_pgsql', 'serverVersion' => '17.0', 'host' => 'db.test', 'port' => 5433, 'user' => 'app', 'password' => 'secret', 'dbname' => 'shop', 'sslmode' => 'require']), new ProcessCommandRunner());

        self::assertEquals(new PostgresqlClient('shop', ['dbname' => 'shop', 'host' => 'db.test', 'port' => '5433', 'user' => 'app', 'password' => 'secret'], new ProcessCommandRunner()), $client);
    }

    public function testAnInMemoryDatabaseOrAnotherPlatformIsRefused(): void
    {
        foreach ([['driver' => 'pdo_sqlite', 'memory' => true], ['driver' => 'pdo_sqlite', 'path' => ''], ['driver' => 'pdo_sqlite', 'path' => ':memory:']] as $parameters) {
            try {
                DatabaseClients::forConnection(DriverManager::getConnection($parameters), new ProcessCommandRunner());
                self::fail(json_encode($parameters, \JSON_THROW_ON_ERROR));
            } catch (BackupFailed $backupFailed) {
                self::assertSame('An in-memory SQLite database has no file to back up or restore.', $backupFailed->getMessage());
            }
        }

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('The bundle backs up and restores SQLite, MySQL, MariaDB and PostgreSQL databases, not one on Doctrine\DBAL\Platforms\OraclePlatform.');
        DatabaseClients::forConnection(DriverManager::getConnection(['driver' => 'oci8', 'serverVersion' => '19']), new ProcessCommandRunner());
    }
}
