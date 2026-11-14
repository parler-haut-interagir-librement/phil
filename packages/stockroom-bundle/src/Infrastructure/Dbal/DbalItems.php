<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Item;
use Phil\Stockroom\ItemCondition;
use Phil\Stockroom\Tracking;
use UnexpectedValueException;

/**
 * The items in a table of a DBAL connection, one row each; the code of each label unique, read by its own index. It
 * keeps no state between calls.
 */
final readonly class DbalItems implements Items
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when the row is not an item the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    #[Override]
    public function get(string $id): ?Item
    {
        return $this->find('id', $id);
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when the row is not an item the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    #[Override]
    public function byCode(string $code): ?Item
    {
        return $this->find('code', $code);
    }

    /**
     * @throws Exception when the database fails, a unique violation among them when another item carries the code
     */
    #[Override]
    public function save(Item $item): void
    {
        $values = ['code' => $item->code, 'name' => $item->name, 'tracking' => $item->tracking->value, 'home' => $item->home, 'item_condition' => $item->condition->value];

        if (false === $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE id = ?', $this->tables->items), [$item->id])) {
            $this->connection->insert($this->tables->items, ['id' => $item->id] + $values);

            return;
        }

        $this->connection->update($this->tables->items, $values, ['id' => $item->id]);
    }

    /**
     * @param 'code'|'id' $column
     *
     * @throws Exception                when the database fails
     * @throws InvalidStock             when the row is not an item the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    private function find(string $column, string $value): ?Item
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT id, code, name, tracking, home, item_condition FROM %s WHERE %s = ?', $this->tables->items, $column), [$value]);

        if (false === $row) {
            return null;
        }

        $row = new Row($row);
        $tracking = Tracking::tryFrom($row->string('tracking')) ?? throw new UnexpectedValueException('The tracking of an item of the stockroom is not one.');
        $condition = ItemCondition::tryFrom($row->string('item_condition')) ?? throw new UnexpectedValueException('The condition of an item of the stockroom is not one.');

        return new Item($row->string('id'), $row->string('code'), $row->string('name'), $tracking, $row->optional('home'), $condition);
    }
}
