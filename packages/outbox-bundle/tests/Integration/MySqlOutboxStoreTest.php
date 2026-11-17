<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Integration;

use Override;
use Phil\Outbox\Test\OutboxStoreTestCase;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Phil\OutboxBundle\Infrastructure\Doctrine\Rows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The store on MySQL 8.4, at `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalOutboxStore::class)]
#[CoversClass(OutboxTable::class)]
#[CoversClass(Rows::class)]
#[CoversClass(OutboxStoreTestCase::class)]
#[Group('mysql')]
final class MySqlOutboxStoreTest extends ServerOutboxStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
