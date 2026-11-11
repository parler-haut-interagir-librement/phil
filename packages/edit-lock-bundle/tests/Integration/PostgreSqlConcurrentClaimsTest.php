<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use Phil\EditLockBundle\Tests\Unit\Infrastructure\ConcurrentClaimsTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL, through `pdo_pgsql`: row locks, a wait cut to a tenth of a second by `lock_timeout`.
 *
 *     PHIL_TEST_POSTGRESQL_URL='postgresql://phil:secret@127.0.0.1:15432/phil' vendor/bin/phpunit packages/edit-lock-bundle/tests/Integration
 */
#[CoversClass(DoctrineLeaseRepository::class)]
#[CoversClass(EditLocks::class)]
#[Group('postgresql')]
final class PostgreSqlConcurrentClaimsTest extends ConcurrentClaimsTestCase
{
    #[Override]
    protected function connect(): Connection
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL to run this test against a real server.');
        }

        $connection = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $connection->executeStatement("SET lock_timeout = '100ms'");

        return $connection;
    }
}
