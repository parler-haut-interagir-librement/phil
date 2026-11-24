<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Content;

use Override;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\FeedBundle\Infrastructure\Content\Contract\ContentEntries;
use Phil\LocaleDimensions\Locale;

/**
 * The contents of `phil/content-bundle`, read through `PublishedContents` — one page of rows in a few queries — and
 * their addresses through `ContentUrlGenerator`, in one lookup of routes per page.
 *
 * @internal
 */
final readonly class PublishedContentEntries implements ContentEntries
{
    public function __construct(
        private PublishedContents $published,
        private ContentUrlGenerator $urls,
    ) {
    }

    #[Override]
    public function latest(string $kind, Locale $locale, int $offset, int $limit): array
    {
        return $this->published->find(new PublishedRowQuery($kind, $locale, PublishedOrder::LatestPublished, withSlug: true, offset: $offset, limit: $limit));
    }

    #[Override]
    public function addresses(array $contents, Locale $locale): array
    {
        return $this->urls->generateAll(array_map(static fn (PublishedContent $content): ContentReference => $content->reference, $contents), $locale, true);
    }
}
