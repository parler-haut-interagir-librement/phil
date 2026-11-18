<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\AccessBundle\Application\Contract\TuplePartitions;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\ResourcePermissions\Partition;

/**
 * The partitions of the tuple table of `phil/resource-permissions`' `DbalTupleStore`, read from its `scope` column:
 * one statement, on the column that leads the primary key, each partition once, in the column's order. It reads names
 * only, never a tuple. The table's name follows the store's rule: up to 63 lowercase letters, digits or `_`, not
 * starting with a digit.
 */
final readonly class DbalTuplePartitions implements TuplePartitions
{
    private string $table;

    public function __construct(
        private Connection $connection,
        string $table,
    ) {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $table)) {
            throw InvalidAccess::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table));
        }

        $this->table = $table;
    }

    #[Override]
    public function partitions(): array
    {
        $partitions = [];

        foreach ($this->connection->fetchFirstColumn(\sprintf('SELECT DISTINCT scope FROM %s WHERE scope <> ? ORDER BY scope', $this->table), [Partition::DEFAULT]) as $partition) {
            \assert(\is_string($partition));
            $partitions[] = $partition;
        }

        return $partitions;
    }
}
