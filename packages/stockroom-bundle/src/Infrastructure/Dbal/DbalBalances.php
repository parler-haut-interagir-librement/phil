<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Place;
use UnexpectedValueException;

/**
 * The balances in a table of a DBAL connection: one row per item and place that holds pieces of it, the place kept as
 * its key; a quantity set to zero deletes the row. The places of an item are read by the primary key, the items of a
 * place by a second index. It keeps no state between calls.
 *
 * Reading a quantity and writing the next one are two statements: `StockroomTransactions` runs a movement in one
 * transaction, the row of its item locked, so that two movements of one item never write over each other.
 */
final readonly class DbalBalances implements Balances
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @throws Exception                when the database fails
     * @throws UnexpectedValueException when the quantity kept is not a whole number
     */
    #[Override]
    public function quantity(string $item, Place $place): int
    {
        $quantity = $this->connection->fetchAssociative(\sprintf('SELECT quantity FROM %s WHERE item = ? AND place = ?', $this->tables->balances), [$item, $place->key()]);

        return false === $quantity ? 0 : new Row($quantity)->integer('quantity');
    }

    /**
     * @throws Exception                when the database fails
     * @throws UnexpectedValueException when the quantity kept is not a whole number
     */
    #[Override]
    public function set(string $item, Place $place, int $quantity): void
    {
        $key = ['item' => $item, 'place' => $place->key()];

        if ($quantity < 1) {
            $this->connection->delete($this->tables->balances, $key);

            return;
        }

        if (false === $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE item = ? AND place = ?', $this->tables->balances), array_values($key))) {
            $this->connection->insert($this->tables->balances, $key + ['quantity' => $quantity], ['quantity' => ParameterType::INTEGER]);

            return;
        }

        $this->connection->update($this->tables->balances, ['quantity' => $quantity], $key, ['quantity' => ParameterType::INTEGER]);
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a place kept is not the key of one
     * @throws UnexpectedValueException when a column is not of its type
     */
    #[Override]
    public function of(string $item): array
    {
        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT place, quantity FROM %s WHERE item = ? ORDER BY place', $this->tables->balances), [$item]);

        return array_map(static function (array $row) use ($item): Holding {
            $row = new Row($row);

            return new Holding($item, Place::fromKey($row->string('place')), $row->integer('quantity'));
        }, $rows);
    }

    /**
     * @throws Exception                when the database fails
     * @throws UnexpectedValueException when a column is not of its type
     */
    #[Override]
    public function at(Place $place, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('item', 'quantity')
            ->from($this->tables->balances)
            ->where('place = :place')
            ->orderBy('item')
            ->setParameter('place', $place->key())
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        $holdings = [];

        foreach ($rows as $row) {
            $row = new Row($row);
            $holdings[] = new Holding($row->string('item'), $place, $row->integer('quantity'));
        }

        return $holdings;
    }
}
