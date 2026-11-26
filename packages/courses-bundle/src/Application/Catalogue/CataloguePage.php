<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Catalogue;

use Phil\CoursesBundle\Domain\Model\Catalogue;

/**
 * One page of a catalogue as a query lists it: its courses, how many the query kept in all, the page and the number of
 * pages, and every tag of the catalogue, to filter by.
 */
final readonly class CataloguePage
{
    /**
     * @param list<CatalogueItem> $items
     * @param int<0, max>         $total
     * @param int<1, max>         $pages
     * @param list<string>        $tags  sorted
     */
    public function __construct(
        public Catalogue $catalogue,
        public CatalogueQuery $query,
        public array $items,
        public int $total,
        public int $pages,
        public array $tags,
    ) {
    }

    public function hasPrevious(): bool
    {
        return $this->query->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->query->page < $this->pages;
    }
}
