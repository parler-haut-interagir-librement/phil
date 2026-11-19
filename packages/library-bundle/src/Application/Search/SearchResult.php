<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Search;

/**
 * One page of a search: its rows, the number of titles found in all, and where the page stands.
 */
final readonly class SearchResult
{
    /**
     * @param list<TitleRow> $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * The number of pages, one at least.
     */
    public function pages(): int
    {
        return max(1, intdiv($this->total + $this->perPage - 1, $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }
}
