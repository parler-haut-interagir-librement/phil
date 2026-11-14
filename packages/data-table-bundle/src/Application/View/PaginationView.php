<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

/**
 * The pagination of a table.
 */
final readonly class PaginationView
{
    /**
     * @param int                 $first     the number of the first row shown, 0 when there is none
     * @param int                 $last      the number of the last row shown
     * @param list<PageLink|null> $links     the page numbers around the current one, null for a gap
     * @param bool                $truncated whether more rows match than the furthest page reaches
     */
    public function __construct(
        public int $page,
        public int $lastPage,
        public int $first,
        public int $last,
        public ?string $previousHref,
        public ?string $nextHref,
        public array $links,
        public bool $truncated,
    ) {
    }
}
