<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists;
use Phil\StockroomBundle\Infrastructure\Dbal\Row;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The contract held by the bundle's adapters on SQLite in memory.
 */
#[CoversClass(DbalContainers::class)]
#[CoversClass(DbalItems::class)]
#[CoversClass(DbalBalances::class)]
#[CoversClass(DbalJournal::class)]
#[CoversClass(DbalLoadLists::class)]
#[CoversClass(Row::class)]
#[CoversClass(StockroomTables::class)]
final class SqliteStoresTest extends StoresTestCase
{
    #[Override]
    protected function stores(): Stores
    {
        return Stores::sqlite();
    }
}
