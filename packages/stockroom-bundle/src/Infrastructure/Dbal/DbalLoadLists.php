<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Stockroom\Contract\LoadLists;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadList;
use UnexpectedValueException;

/**
 * The load lists in two tables of a DBAL connection: one row per event — a list may hold no line —, and one per line, in
 * the list's order. Saving a list replaces its lines in one transaction, nested in the caller's when one is open. It
 * keeps no state between calls.
 */
final readonly class DbalLoadLists implements LoadLists
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a row is not a line the library keeps
     * @throws UnexpectedValueException when a column is not of its type
     */
    #[Override]
    public function get(string $event): ?LoadList
    {
        if (false === $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE event = ?', $this->tables->loadLists), [$event])) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT item, quantity, from_container FROM %s WHERE event = ? ORDER BY line', $this->tables->loadLines),
            [$event],
        );

        return new LoadList($event, array_map(static function (array $row): LoadLine {
            $row = new Row($row);

            return new LoadLine($row->string('item'), $row->integer('quantity'), $row->optional('from_container'));
        }, $rows));
    }

    /**
     * @throws Exception when the database fails; nothing of the list is then written
     */
    #[Override]
    public function save(LoadList $list): void
    {
        $this->connection->transactional(function (Connection $connection) use ($list): void {
            if (false === $connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE event = ?', $this->tables->loadLists), [$list->event])) {
                $connection->insert($this->tables->loadLists, ['event' => $list->event]);
            }

            $connection->delete($this->tables->loadLines, ['event' => $list->event]);

            foreach ($list->lines as $position => $line) {
                $connection->insert(
                    $this->tables->loadLines,
                    ['event' => $list->event, 'line' => $position, 'item' => $line->item, 'quantity' => $line->quantity, 'from_container' => $line->from],
                    ['line' => ParameterType::INTEGER, 'quantity' => ParameterType::INTEGER],
                );
            }
        });
    }
}
