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
 * MySQL 8 with InnoDB, through `pdo_mysql`: row locks, a wait cut to one second.
 *
 *     PHIL_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:13306/phil' vendor/bin/phpunit packages/edit-lock-bundle/tests/Integration
 */
#[CoversClass(DoctrineLeaseRepository::class)]
#[CoversClass(EditLocks::class)]
#[Group('mysql')]
final class MySqlConcurrentClaimsTest extends ConcurrentClaimsTestCase
{
    #[Override]
    protected function connect(): Connection
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql'])->parse($url));
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }
}
