<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;

/**
 * What a source is asked for: at most `limit` entries, the most recent first, from the page `page` on — page 1 holds
 * the most recent, page 2 the `limit` after them. A source that keeps no older pages answers page 1 only.
 */
final readonly class FeedQuery
{
    public const int MAX_LIMIT = 500;

    public const int MAX_PAGE = 1000;

    /**
     * @throws InvalidFeed when the limit is not 1 to 500, or the page not 1 to 1000
     */
    public function __construct(
        public int $limit,
        public int $page = 1,
    ) {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidFeed::because('limit', \sprintf('1 to %d entries', self::MAX_LIMIT), (string) $limit);
        }

        if ($page < 1 || $page > self::MAX_PAGE) {
            throw InvalidFeed::because('page', \sprintf('1 to %d', self::MAX_PAGE), (string) $page);
        }
    }

    /**
     * How many entries come before this page: `(page - 1) × limit`.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
