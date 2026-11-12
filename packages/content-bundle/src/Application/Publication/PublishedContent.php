<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * A content published in one locale, merged as its page shows it: what a sitemap and a feed read.
 */
final readonly class PublishedContent
{
    public function __construct(
        public ContentReference $reference,
        public Locale $locale,
        public DimensionContent $content,
    ) {
    }

    /**
     * When it was last published; `null` for a row without the workflow capability.
     */
    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->content instanceof WorkflowCapable ? $this->content->getWorkflowPublished() : null;
    }

    /**
     * Its title: the excerpt's, else the SEO title, else its reference.
     */
    public function title(): string
    {
        $excerpt = $this->content instanceof ExcerptCapable ? $this->content->getExcerptTitle() : null;
        $seo = $this->content instanceof SeoCapable ? $this->content->getSeoTitle() : null;

        return $excerpt ?? $seo ?? $this->reference->toString();
    }

    /**
     * Its summary: the excerpt's description, else the SEO description.
     */
    public function summary(): ?string
    {
        $excerpt = $this->content instanceof ExcerptCapable ? $this->content->getExcerptDescription() : null;

        return $excerpt ?? ($this->content instanceof SeoCapable ? $this->content->getSeoDescription() : null);
    }

    /**
     * Whether its SEO asks to keep it out of sitemaps: hidden from them, or not to be indexed.
     */
    public function isHiddenFromSitemaps(): bool
    {
        return $this->content instanceof SeoCapable && (true === $this->content->getSeoHideInSitemap() || true === $this->content->getSeoNoIndex());
    }
}
