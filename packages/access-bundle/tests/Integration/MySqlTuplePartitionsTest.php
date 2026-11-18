<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AccessBundle\Infrastructure\Dbal\DbalTuplePartitions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The partitions of a tuple table on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalTuplePartitions::class)]
final class MySqlTuplePartitionsTest extends TuplePartitionsTestCase
{
    #[Override]
    protected function connect(): Connection
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
