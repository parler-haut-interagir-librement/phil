<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\ValueObject;

use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\LocaleDimensions\Locale;

/**
 * Which published rows of one kind of content a locale serves: for each root of the kind with a live row of the
 * locale, that row in the highest version the locale was published from — no fallback, a sitemap lists a locale's
 * own pages and a feed its own entries.
 *
 * - `rootCriteria` keeps the roots whose fields equal the values given (`['genre' => 'podcast']`); a field the root
 *   does not map is an error of the adapter.
 * - `resourceIds` keeps the roots of these identifiers only; `null` keeps every root, an empty list none.
 * - `withSlug` keeps the rows that carry a slug, the only ones a route can serve; a row class without the routable
 *   capability then has none.
 * - `offset` and `limit` cut a page out of the order.
 */
final readonly class PublishedRowQuery
{
    /**
     * @param array<string, bool|int|string> $rootCriteria
     * @param list<string>|null              $resourceIds
     *
     * @throws InvalidContent when the offset is negative or the limit is not positive
     */
    public function __construct(
        public string $resourceKey,
        public Locale $locale,
        public PublishedOrder $order = PublishedOrder::Root,
        public array $rootCriteria = [],
        public ?array $resourceIds = null,
        public bool $withSlug = false,
        public int $offset = 0,
        public ?int $limit = null,
    ) {
        if ($offset < 0) {
            throw InvalidContent::because(\sprintf('The offset of a query of published rows is 0 or more; got %d.', $offset));
        }

        if (null !== $limit && $limit < 1) {
            throw InvalidContent::because(\sprintf('The limit of a query of published rows is 1 or more; got %d.', $limit));
        }
    }

    /**
     * The same query, for the page that starts at `$offset` and holds at most `$limit` rows.
     */
    public function page(int $offset, ?int $limit): self
    {
        return new self($this->resourceKey, $this->locale, $this->order, $this->rootCriteria, $this->resourceIds, $this->withSlug, $offset, $limit);
    }
}
