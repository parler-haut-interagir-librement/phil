<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Lti\Contract\LineItemStore;
use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Service\Ags\LineItem;

/**
 * The line items of the platform, in a table; deleting one deletes its scores in the same transaction.
 */
final readonly class DbalLineItemStore implements LineItemStore
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
    ) {
    }

    #[Override]
    public function list(string $clientId, string $contextId, ?string $resourceLinkId, ?string $resourceId, ?string $tag, int $limit, int $offset): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tables->lineItems)
            ->where('client_id = :client', 'context_id = :context')
            ->setParameter('client', $clientId)
            ->setParameter('context', $contextId)
            ->orderBy('id')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        foreach (['resource_link_id' => $resourceLinkId, 'resource_id' => $resourceId, 'tag' => $tag] as $column => $value) {
            if (null !== $value) {
                $query->andWhere($column.' = :'.$column)->setParameter($column, $value);
            }
        }

        return array_map($this->hydrate(...), $query->executeQuery()->fetchAllAssociative());
    }

    #[Override]
    public function find(string $clientId, string $contextId, string $id): ?StoredLineItem
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s WHERE id = ? AND client_id = ? AND context_id = ?', $this->tables->lineItems), [$id, $clientId, $contextId]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function save(StoredLineItem $lineItem): void
    {
        $item = $lineItem->lineItem;
        $row = [
            'client_id' => $lineItem->clientId,
            'context_id' => $lineItem->contextId,
            'score_maximum' => $item->scoreMaximum,
            'label' => $item->label,
            'resource_id' => $item->resourceId,
            'resource_link_id' => $item->resourceLinkId,
            'tag' => $item->tag,
            'start_at' => $item->startDateTime?->format(DateTimeInterface::ATOM),
            'end_at' => $item->endDateTime?->format(DateTimeInterface::ATOM),
            'grades_released' => null === $item->gradesReleased ? null : (int) $item->gradesReleased,
        ];

        $this->connection->transactional(function (Connection $connection) use ($lineItem, $row): void {
            if (0 === $connection->update($this->tables->lineItems, $row, ['id' => $lineItem->id])) {
                $connection->insert($this->tables->lineItems, ['id' => $lineItem->id] + $row);
            }
        });
    }

    #[Override]
    public function delete(StoredLineItem $lineItem): void
    {
        $this->connection->transactional(function (Connection $connection) use ($lineItem): void {
            $connection->delete($this->tables->scores, ['line_item_id' => $lineItem->id]);
            $connection->delete($this->tables->lineItems, ['id' => $lineItem->id]);
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StoredLineItem
    {
        $start = Row::optionalString($row, 'start_at');
        $end = Row::optionalString($row, 'end_at');
        $released = Row::optionalInt($row, 'grades_released');

        return new StoredLineItem(Row::string($row, 'id'), Row::string($row, 'client_id'), Row::string($row, 'context_id'), new LineItem(
            Row::float($row, 'score_maximum'),
            Row::string($row, 'label'),
            null,
            Row::optionalString($row, 'resource_id'),
            Row::optionalString($row, 'resource_link_id'),
            Row::optionalString($row, 'tag'),
            null === $start ? null : new DateTimeImmutable($start),
            null === $end ? null : new DateTimeImmutable($end),
            null === $released ? null : 1 === $released,
        ));
    }
}
