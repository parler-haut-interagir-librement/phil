<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\ValueObject;

use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;

/**
 * One page of the archive: its issues, how many the filter matches in all, and where the page stands.
 */
final readonly class IssuePage
{
    /**
     * @param list<ArchivedIssue> $issues
     */
    public function __construct(
        public array $issues,
        public int $total,
        public ArchiveFilter $filter,
    ) {
    }

    /**
     * How many pages the filter fills, at least one, at most the deepest page read.
     */
    public function pages(): int
    {
        return max(1, min(ArchiveFilter::MAX_PAGE, intdiv($this->total + $this->filter->pageSize - 1, $this->filter->pageSize)));
    }

    public function hasPrevious(): bool
    {
        return $this->filter->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->filter->page < $this->pages();
    }
}
