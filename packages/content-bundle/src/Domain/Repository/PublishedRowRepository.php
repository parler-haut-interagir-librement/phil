<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Repository;

use Phil\ContentBundle\Domain\ValueObject\PublishedRow;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;

/**
 * Reads the published rows of a kind of content in a few queries, whatever the number of roots: what the sitemap,
 * the feeds and the lists of an application read. `Infrastructure\Doctrine\DoctrinePublishedRowRepository` is the
 * Doctrine adapter.
 */
interface PublishedRowRepository
{
    /**
     * @return list<PublishedRow> in the order of the query
     */
    public function findPublished(PublishedRowQuery $query): array;
}
