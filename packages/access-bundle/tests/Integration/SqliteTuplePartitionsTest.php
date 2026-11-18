<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\AccessBundle\Infrastructure\Dbal\DbalTuplePartitions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The partitions of a tuple table on SQLite, in memory.
 */
#[CoversClass(DbalTuplePartitions::class)]
final class SqliteTuplePartitionsTest extends TuplePartitionsTestCase
{
    #[Override]
    protected function connect(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
