<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Url\Url;

/**
 * The implementation of `SeoCapable`, without any persistence mapping.
 *
 * The canonical URL is held as its string, the form its column stores, and read back as a `Url`.
 *
 * @phpstan-require-implements SeoCapable
 */
trait SeoTrait
{
    private ?string $seoTitle = null;

    private ?string $seoDescription = null;

    /**
     * @var list<string>
     */
    private array $seoKeywords = [];

    private ?string $seoCanonicalUrl = null;

    private ?bool $seoNoIndex = null;

    private ?bool $seoNoFollow = null;

    private ?bool $seoHideInSitemap = null;

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): void
    {
        $this->seoTitle = null === $seoTitle ? null : CapabilityText::line($seoTitle, 'The SEO title');
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): void
    {
        $this->seoDescription = null === $seoDescription ? null : CapabilityText::text($seoDescription, 'The SEO description');
    }

    /**
     * @return list<string>
     */
    public function getSeoKeywords(): array
    {
        return $this->seoKeywords;
    }

    /**
     * @param list<string> $seoKeywords
     */
    public function setSeoKeywords(array $seoKeywords): void
    {
        $kept = [];

        foreach ($seoKeywords as $keyword) {
            CapabilityText::line($keyword, 'An SEO keyword');

            if (str_contains($keyword, ',')) {
                throw InvalidContent::because(\sprintf('The SEO keyword "%s" holds a comma, which separates keywords.', $keyword));
            }

            $kept[$keyword] = $keyword;
        }

        $this->seoKeywords = array_values($kept);
    }

    public function getSeoCanonicalUrl(): ?Url
    {
        return null === $this->seoCanonicalUrl ? null : Url::fromString($this->seoCanonicalUrl);
    }

    public function setSeoCanonicalUrl(?Url $seoCanonicalUrl): void
    {
        $this->seoCanonicalUrl = $seoCanonicalUrl?->toString();
    }

    public function getSeoNoIndex(): ?bool
    {
        return $this->seoNoIndex;
    }

    public function setSeoNoIndex(?bool $seoNoIndex): void
    {
        $this->seoNoIndex = $seoNoIndex;
    }

    public function getSeoNoFollow(): ?bool
    {
        return $this->seoNoFollow;
    }

    public function setSeoNoFollow(?bool $seoNoFollow): void
    {
        $this->seoNoFollow = $seoNoFollow;
    }

    public function getSeoHideInSitemap(): ?bool
    {
        return $this->seoHideInSitemap;
    }

    public function setSeoHideInSitemap(?bool $seoHideInSitemap): void
    {
        $this->seoHideInSitemap = $seoHideInSitemap;
    }
}
