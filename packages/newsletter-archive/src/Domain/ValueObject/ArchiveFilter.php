<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\ValueObject;

use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;

/**
 * Which page of a site's archive is read: the issues published on one list, by its key, and under one tag, when they
 * are given, newest first, a page of a bounded size at a bounded depth.
 */
final readonly class ArchiveFilter
{
    public const int MAX_TAGS = 16;

    public const int MAX_PAGE_SIZE = 100;

    /** The deepest page read, so that a crawler never makes the database skip millions of rows. */
    public const int MAX_PAGE = 1000;

    public function __construct(
        public string $site,
        public ?string $listKey = null,
        public ?Tag $tag = null,
        public int $page = 1,
        public int $pageSize = 20,
    ) {
        if ($page < 1 || $page > self::MAX_PAGE) {
            throw InvalidArchiveValue::because(\sprintf('A page of the archive is a number from 1 to %d.', self::MAX_PAGE));
        }

        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw InvalidArchiveValue::because(\sprintf('A page of the archive holds 1 to %d issues.', self::MAX_PAGE_SIZE));
        }
    }

    /**
     * How many issues the page skips.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }
}
