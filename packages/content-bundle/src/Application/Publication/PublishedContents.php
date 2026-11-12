<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\Repository\PublishedRowRepository;
use Phil\ContentBundle\Domain\ValueObject\PublishedRow;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * The contents of a kind a locale publishes itself — no fallback: a sitemap lists a locale's own pages, a feed its
 * own entries —, and the draft of one content in one locale, for its preview.
 *
 * Published contents are read through `PublishedRowRepository`, a page of rows in a few queries: `find()` reads
 * one page, `chunks()` walks a whole kind page by page, so neither the number of queries nor the size of one grows
 * with the number of contents.
 */
final readonly class PublishedContents
{
    /**
     * How many contents `chunks()` reads at once by default.
     */
    public const int CHUNK_SIZE = 500;

    public function __construct(
        private PublishedRowRepository $published,
        private ContentRootRepository $roots,
        private DimensionContentRepository $rows,
        private ContentAggregator $aggregator,
    ) {
    }

    /**
     * @return list<PublishedContent> in the order of the query
     */
    public function find(PublishedRowQuery $query): array
    {
        return array_map(
            fn (PublishedRow $row): PublishedContent => new PublishedContent(
                $this->roots->referenceOf($row->localized->root()),
                $query->locale,
                $this->aggregator->mergeRows($row->localized, $row->shared),
            ),
            $this->published->findPublished($query),
        );
    }

    /**
     * Every content of a kind the locale publishes, in pages of at most `$size`, in the order of the roots.
     *
     * @param positive-int $size
     *
     * @return iterable<list<PublishedContent>>
     */
    public function chunks(string $resourceKey, Locale $locale, int $size = self::CHUNK_SIZE): iterable
    {
        $query = new PublishedRowQuery($resourceKey, $locale, PublishedOrder::Root);

        for ($offset = 0;; $offset += $size) {
            $chunk = $this->find($query->page($offset, $size));

            if ([] !== $chunk) {
                yield $chunk;
            }

            if (\count($chunk) < $size) {
                return;
            }
        }
    }

    /**
     * The most recently published first; those never published last, in the order of the roots. With `$withSlug`,
     * only those with a slug, the ones a route serves: what a feed lists.
     *
     * @return list<PublishedContent>
     */
    public function latest(string $resourceKey, Locale $locale, int $limit, bool $withSlug = false): array
    {
        return $this->find(new PublishedRowQuery($resourceKey, $locale, PublishedOrder::LatestPublished, withSlug: $withSlug, limit: $limit));
    }

    /**
     * The draft of a content in one locale, merged as its page would show it once published; `null` when the
     * locale has no draft.
     */
    public function draft(ContentRoot $root, Locale $locale): ?DimensionContent
    {
        $version = $this->rows->latestLocalizedVersion($root, Stage::Draft, $locale);

        return $version instanceof Version ? $this->aggregator->aggregate($root, DimensionKey::localized($locale, Stage::Draft, $version)) : null;
    }
}
