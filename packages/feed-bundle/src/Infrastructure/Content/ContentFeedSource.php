<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Content;

use DateTimeImmutable;
use Override;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\FeedBundle\Infrastructure\Content\Contract\ContentEntries;
use Phil\LocaleDimensions\Locale;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

/**
 * A feed of the contents of one kind of `phil/content-bundle` in one locale: those the locale publishes itself, with a
 * slug, the most recently published first, less those whose SEO says `noindex`.
 *
 * Each entry has the content's title and summary as its page shows them, its HTML body, its absolute address, and when
 * it was last published. Its identifier is `tag:{authority}:{reference}` when the feed has a tag authority — it then
 * survives a change of slug —, else its address. A content without an address, or never published, is left out.
 *
 * A page belongs to the feed when its owner identifier is a reference of the kind and its language is the locale's.
 */
final readonly class ContentFeedSource implements FeedSource
{
    public const string TAG_AUTHORITY = '/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?,\d{4}(-\d{2}(-\d{2})?)?$/D';

    public function __construct(
        private ContentEntries $entries,
        private string $name,
        private string $kind,
        private Locale $locale,
        private Channel $channel,
        private ?string $tagAuthority = null,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function channel(): Channel
    {
        return $this->channel;
    }

    #[Override]
    public function items(FeedQuery $query): FeedPage
    {
        $contents = $this->entries->latest($this->kind, $this->locale, $query->offset(), $query->limit + 1);
        $hasNextPage = \count($contents) > $query->limit;
        $listed = array_values(array_filter(\array_slice($contents, 0, $query->limit), static fn (PublishedContent $content): bool => !$content->content instanceof SeoCapable || true !== $content->content->getSeoNoIndex()));
        $addresses = $this->entries->addresses($listed, $this->locale);
        $items = [];

        foreach ($listed as $content) {
            $reference = $content->reference->toString();
            $address = $addresses[$reference] ?? null;
            $published = $content->publishedAt();

            if (null !== $address && $published instanceof DateTimeImmutable) {
                $items[] = $this->item($content, $reference, Url::fromString($address, $this->allowLocalhost), $published);
            }
        }

        return new FeedPage($items, $hasNextPage);
    }

    #[Override]
    public function includes(PageEvent $page): bool
    {
        try {
            $reference = ContentReference::fromString($page->ownerId);
        } catch (InvalidContent) {
            return false;
        }

        return $reference->resourceKey === $this->kind && $page->locale === $this->locale->toLanguageTag();
    }

    private function item(PublishedContent $content, string $reference, Url $url, DateTimeImmutable $published): FeedItem
    {
        $row = $content->content;

        return new FeedItem(
            null === $this->tagAuthority ? $url->toString() : 'tag:'.$this->tagAuthority.':'.$reference,
            $content->title(),
            $url,
            $published,
            summary: $content->summary(),
            contentHtml: $row instanceof BodyCapable ? $row->getBody()?->html : null,
        );
    }
}
