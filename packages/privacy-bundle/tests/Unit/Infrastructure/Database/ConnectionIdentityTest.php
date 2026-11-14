<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Phil\PrivacyBundle\Infrastructure\Database\ConnectionIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The identity the production guard reads, drawn from the connection's parameters without connecting.
 */
#[CoversClass(ConnectionIdentity::class)]
final class ConnectionIdentityTest extends TestCase
{
    public function testEachPlatformIsNamedWithItsHostAndDatabase(): void
    {
        foreach ([
            'mysql database shop on db.test' => ['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0', 'host' => 'db.test', 'dbname' => 'shop'],
            'mariadb database shop' => ['driver' => 'pdo_mysql', 'serverVersion' => '11.4.2-MariaDB', 'dbname' => 'shop'],
            'postgresql database shop on db.test' => ['driver' => 'pdo_pgsql', 'serverVersion' => '17.0', 'host' => 'db.test', 'dbname' => 'shop'],
            'postgresql database ' => ['driver' => 'pdo_pgsql', 'serverVersion' => '17.0'],
            'sqlite database /data/app.sqlite' => ['driver' => 'pdo_sqlite', 'path' => '/data/app.sqlite'],
            'sqlite database :memory:' => ['driver' => 'pdo_sqlite', 'memory' => true],
            OraclePlatform::class.' database app' => ['driver' => 'oci8', 'serverVersion' => '19', 'dbname' => 'app'],
        ] as $description => $parameters) {
            self::assertSame($description, ConnectionIdentity::of(DriverManager::getConnection($parameters))->describe());
        }

        self::assertSame(':memory:', ConnectionIdentity::of(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => '']))->name);
        self::assertNull(ConnectionIdentity::of(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => '/a.sqlite', 'host' => 'ignored']))->host);
    }
}
