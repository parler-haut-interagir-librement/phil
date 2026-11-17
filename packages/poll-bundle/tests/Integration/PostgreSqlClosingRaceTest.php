<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The race on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(Polls::class)]
#[CoversClass(DoctrinePollRepository::class)]
final class PostgreSqlClosingRaceTest extends ClosingRaceTestCase
{
    #[Override]
    protected function connect(): Connection
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL to run this test against a real server.');
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
