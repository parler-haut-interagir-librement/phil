<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Url\Url;

/**
 * A row that carries what search engines read: a title and a description, keywords, a canonical URL, and
 * three directives — `noindex`, `nofollow`, and leaving the page out of the sitemap.
 *
 * The values usually live on the localized rows, one per language, and `SeoDataMapper` writes them there.
 *
 * **A directive has three states.** `null` says the row does not set it, so the merged row keeps what the
 * shared row said; `true` and `false` are explicit, and a localized `false` lifts a shared `true`. A directive
 * nobody sets reads as `false` once normalized.
 *
 * Use `SeoTrait` for the implementation. Text values are refused, never corrected, when they are not
 * trimmed, empty, or longer than their column (see `CapabilityText`).
 */
interface SeoCapable
{
    public function getSeoTitle(): ?string;

    /**
     * @throws InvalidContent
     */
    public function setSeoTitle(?string $seoTitle): void;

    public function getSeoDescription(): ?string;

    /**
     * @throws InvalidContent
     */
    public function setSeoDescription(?string $seoDescription): void;

    /**
     * @return list<string>
     */
    public function getSeoKeywords(): array;

    /**
     * @param list<string> $seoKeywords in order; a repeated keyword is kept once
     *
     * @throws InvalidContent when a keyword is not a line, or holds a comma, which separates keywords
     */
    public function setSeoKeywords(array $seoKeywords): void;

    public function getSeoCanonicalUrl(): ?Url;

    public function setSeoCanonicalUrl(?Url $seoCanonicalUrl): void;

    public function getSeoNoIndex(): ?bool;

    public function setSeoNoIndex(?bool $seoNoIndex): void;

    public function getSeoNoFollow(): ?bool;

    public function setSeoNoFollow(?bool $seoNoFollow): void;

    public function getSeoHideInSitemap(): ?bool;

    public function setSeoHideInSitemap(?bool $seoHideInSitemap): void;
}
