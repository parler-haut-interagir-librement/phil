<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Stockroom\Contract\Journal;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Place;
use UnexpectedValueException;

/**
 * The journal in a table of a DBAL connection, written once: the adapter inserts, and has no statement that changes or
 * deletes a movement. Each row takes the next value of an auto-incremented position, which orders the journal as it was
 * written; the movements of an item and of a reference are read by indexes ending with it, the latest first. The moment
 * is kept as microseconds since the Unix epoch, in UTC. It keeps no state between calls.
 */
final readonly class DbalJournal implements Journal
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @throws Exception when the database fails, a unique violation among them when the identifier is written already
     */
    #[Override]
    public function append(Movement $movement): void
    {
        $this->connection->insert($this->tables->movements, [
            'id' => $movement->id,
            'kind' => $movement->kind->value,
            'item' => $movement->item,
            'quantity' => $movement->quantity,
            'from_place' => $movement->from?->key(),
            'to_place' => $movement->to?->key(),
            'moved_at' => Row::microseconds($movement->at),
            'reference' => $movement->reference,
            'note' => $movement->note,
        ], ['quantity' => ParameterType::INTEGER, 'moved_at' => ParameterType::INTEGER]);
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a row is not a movement the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    #[Override]
    public function ofItem(string $item, int $limit): array
    {
        return $this->latest('item', $item, $limit);
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a row is not a movement the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    #[Override]
    public function ofReference(string $reference, int $limit): array
    {
        return $this->latest('reference', $reference, $limit);
    }

    /**
     * @param 'item'|'reference' $column
     *
     * @return list<Movement>
     *
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a row is not a movement the library keeps
     * @throws UnexpectedValueException when a column is not of its type or value
     */
    private function latest(string $column, string $value, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'kind', 'item', 'quantity', 'from_place', 'to_place', 'moved_at', 'reference', 'note')
            ->from($this->tables->movements)
            ->where($column.' = :value')
            ->orderBy('position', 'DESC')
            ->setParameter('value', $value)
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        $movements = [];

        foreach ($rows as $row) {
            $row = new Row($row);
            $from = $row->optional('from_place');
            $to = $row->optional('to_place');

            $movements[] = new Movement(
                $row->string('id'),
                MovementKind::tryFrom($row->string('kind')) ?? throw new UnexpectedValueException('The kind of a movement of the stockroom is not one.'),
                $row->string('item'),
                $row->integer('quantity'),
                null === $from ? null : Place::fromKey($from),
                null === $to ? null : Place::fromKey($to),
                $row->moment('moved_at'),
                $row->optional('reference'),
                $row->optional('note'),
            );
        }

        return $movements;
    }
}
