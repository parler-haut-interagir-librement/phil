<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use LogicException;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\PublishedRowRepository;
use Phil\ContentBundle\Domain\ValueObject\PublishedRow;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\Slug\Slug;

/**
 * The published rows of the in-memory roots, read from the flushed rows as the Doctrine adapter reads its table:
 * every root is of the kind `example`, numbered by its reference, which is the order of the roots. Root criteria
 * are a Doctrine matter, refused here.
 */
final readonly class InMemoryPublishedRows implements PublishedRowRepository
{
    public function __construct(
        private InMemoryDimensionContentRepository $rows,
        private InMemoryContentRootRepository $roots,
    ) {
    }

    public function findPublished(PublishedRowQuery $query): array
    {
        if ([] !== $query->rootCriteria) {
            throw new LogicException('The in-memory roots have no field to select by.');
        }

        if ('example' !== $query->resourceKey) {
            return [];
        }

        $latest = [];

        foreach ($this->rows->flushed() as $row) {
            $dimension = $row->dimension();
            $id = $this->roots->referenceOf($row->root())->resourceId;

            if (Stage::Live !== $dimension->stage || $dimension->locale?->value !== $query->locale->value) {
                continue;
            }

            if (null !== $query->resourceIds && !\in_array($id, $query->resourceIds, true)) {
                continue;
            }

            if ($query->withSlug && (!$row instanceof RoutableCapable || !$row->getSlug() instanceof Slug)) {
                continue;
            }

            if (!isset($latest[$id]) || $dimension->version->isAfter($latest[$id]->dimension()->version)) {
                $latest[$id] = $row;
            }
        }

        $found = array_values($latest);
        usort($found, fn (DimensionContent $a, DimensionContent $b): int => $this->compare($query->order, $a, $b));

        return array_map(
            fn (DimensionContent $row): PublishedRow => new PublishedRow($row, $this->rows->find($row->root(), $row->dimension()->unlocalizedCounterpart())),
            \array_slice($found, $query->offset, $query->limit),
        );
    }

    private function compare(PublishedOrder $order, DimensionContent $a, DimensionContent $b): int
    {
        $byOrder = match ($order) {
            PublishedOrder::Root => 0,
            PublishedOrder::LatestPublished => $this->publicationKey($a) <=> $this->publicationKey($b),
            PublishedOrder::Slug => $this->slug($a) <=> $this->slug($b),
        };

        return 0 !== $byOrder ? $byOrder : (int) $this->roots->referenceOf($a->root())->resourceId <=> (int) $this->roots->referenceOf($b->root())->resourceId;
    }

    /**
     * Those published first, the most recent first: never published, then the opposite of the timestamp.
     *
     * @return array{bool, int}
     */
    private function publicationKey(DimensionContent $row): array
    {
        $published = $this->published($row);

        return [!$published instanceof DateTimeImmutable, -($published?->getTimestamp() ?? 0)];
    }

    private function slug(DimensionContent $row): string
    {
        return $row instanceof RoutableCapable ? (string) $row->getSlug()?->value : '';
    }

    private function published(DimensionContent $row): ?DateTimeImmutable
    {
        return $row instanceof WorkflowCapable ? $row->getWorkflowPublished() : null;
    }
}
