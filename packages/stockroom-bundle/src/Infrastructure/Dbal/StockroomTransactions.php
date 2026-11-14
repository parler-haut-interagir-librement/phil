<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use Throwable;

/**
 * Runs the calls of the stockroom in one transaction of its connection, the rows of the items they move locked first.
 *
 * `phil/stockroom` runs no transaction itself: a movement writes its line of the journal and the balances of two places
 * in separate statements, after reading them. Inside `run()`, they are written together or not at all, and two
 * movements of the same item wait for each other — `SELECT … FOR UPDATE` on the items' rows, taken in the order of
 * their identifiers so that two transactions never wait on each other's locks. SQLite, which lets one writer at a time,
 * takes no row lock. Without a connection — the store in memory —, the work simply runs. When the caller has a transaction open on the same connection already — the loans of a library on
 * the same database —, the work joins it: the locks are held until the caller's transaction ends, and the stock moves
 * with the caller's writes or not at all.
 */
final readonly class StockroomTransactions
{
    /** The most items one transaction locks. */
    public const int MAX_ITEMS = 500;

    public function __construct(
        private ?Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @template T
     *
     * @param Closure(): T $work  the calls of the stockroom
     * @param string       $items the identifiers of the items the work moves, locked first
     *
     * @return T what the work returns, once it is committed
     *
     * @throws InvalidArgumentException when the work names more items than a transaction locks
     * @throws Exception                when the database fails; nothing of the work is then kept
     * @throws Throwable                what the work throws; nothing of it is then kept
     */
    public function run(Closure $work, string ...$items): mixed
    {
        $items = array_unique($items);

        if (\count($items) > self::MAX_ITEMS) {
            throw new InvalidArgumentException(\sprintf('A transaction of the stockroom locks at most %d items.', self::MAX_ITEMS));
        }

        sort($items, \SORT_STRING);

        if (!$this->connection instanceof Connection) {
            return $work();
        }

        return $this->connection->transactional(function (Connection $connection) use ($work, $items): mixed {
            if ([] !== $items) {
                $connection->executeQuery($this->lockStatement($connection->getDatabasePlatform(), \count($items)), $items)->free();
            }

            return $work();
        });
    }

    /**
     * The statement that locks the rows of so many items in the order of their identifiers, until the transaction ends;
     * a plain read on SQLite, which lets one writer at a time and knows no `FOR UPDATE`.
     */
    public function lockStatement(AbstractPlatform $platform, int $items): string
    {
        return \sprintf(
            'SELECT id FROM %s WHERE id IN (%s) ORDER BY id%s',
            $this->tables->items,
            implode(', ', array_pad([], max(1, $items), '?')),
            $platform instanceof SQLitePlatform ? '' : ' FOR UPDATE',
        );
    }

    /**
     * Whether the transactions run on this connection: a package that writes on the same one joins them.
     */
    public function uses(Connection $connection): bool
    {
        return $this->connection instanceof Connection && $connection === $this->connection;
    }
}
