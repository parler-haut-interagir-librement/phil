<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Phil\Stockroom\Container;
use Phil\Stockroom\Contract\Containers;
use Phil\Stockroom\Exception\InvalidStock;
use UnexpectedValueException;

/**
 * The containers in a table of a DBAL connection, one row each; the children of a container read by an index on the
 * parent, in the order of their identifiers. It keeps no state between calls.
 */
final readonly class DbalContainers implements Containers
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
    ) {
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when the row is not a container the library keeps
     * @throws UnexpectedValueException when a column is not of its type
     */
    #[Override]
    public function get(string $id): ?Container
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT id, name, parent, code FROM %s WHERE id = ?', $this->tables->containers), [$id]);

        return false === $row ? null : $this->container(new Row($row));
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function save(Container $container): void
    {
        $values = ['name' => $container->name, 'parent' => $container->parent, 'code' => $container->code];

        if (false === $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE id = ?', $this->tables->containers), [$container->id])) {
            $this->connection->insert($this->tables->containers, ['id' => $container->id] + $values);

            return;
        }

        $this->connection->update($this->tables->containers, $values, ['id' => $container->id]);
    }

    /**
     * @throws Exception                when the database fails
     * @throws InvalidStock             when a row is not a container the library keeps
     * @throws UnexpectedValueException when a column is not of its type
     */
    #[Override]
    public function children(string $id, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'parent', 'code')
            ->from($this->tables->containers)
            ->where('parent = :parent')
            ->orderBy('id')
            ->setParameter('parent', $id)
            ->setMaxResults($limit)
            ->fetchAllAssociative();

        $containers = [];

        foreach ($rows as $row) {
            $containers[] = $this->container(new Row($row));
        }

        return $containers;
    }

    /**
     * @throws InvalidStock             when the row is not a container the library keeps
     * @throws UnexpectedValueException when a column is not of its type
     */
    private function container(Row $row): Container
    {
        return new Container($row->string('id'), $row->string('name'), $row->optional('parent'), $row->optional('code'));
    }
}
