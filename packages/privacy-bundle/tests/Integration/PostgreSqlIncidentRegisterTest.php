<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalIncidentRegister::class)]
#[CoversClass(PrivacyTables::class)]
final class PostgreSqlIncidentRegisterTest extends IncidentRegisterTestCase
{
    #[Override]
    protected function open(): Connection
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL to run this test against a real server.');
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
