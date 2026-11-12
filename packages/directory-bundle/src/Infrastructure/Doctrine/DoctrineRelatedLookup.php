<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\DirectoryBundle\Application\RelatedLookup;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;

/**
 * `RelatedLookup` on the Doctrine ORM: the identifier and the related listings of the live rows of a genre that have
 * some, as arrays, no entity hydrated; their JSON is searched here — no portable query searches a JSON column.
 */
final readonly class DoctrineRelatedLookup implements RelatedLookup
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function pointingAt(string $genre, string $relation, string $resourceId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('listing.id AS id', 'row.related AS related')
            ->from(ListingDimension::class, 'row')
            ->join('row.root', 'listing')
            ->where('listing.genre = :genre')
            ->andWhere('row.stage = :stage')
            ->andWhere('row.related IS NOT NULL')
            ->setParameter('genre', $genre)
            ->setParameter('stage', Stage::Live->value)
            ->getQuery()
            ->getArrayResult();

        $found = [];

        foreach ($rows as $row) {
            $id = \is_array($row) ? $row['id'] : null;
            $related = \is_array($row) ? $row['related'] : null;
            $ids = \is_array($related) ? $related[$relation] ?? null : null;

            if ($id instanceof ListingId && \is_array($ids) && \in_array($resourceId, $ids, true)) {
                $found[$id->toString()] = $id->toString();
            }
        }

        return array_values($found);
    }
}
