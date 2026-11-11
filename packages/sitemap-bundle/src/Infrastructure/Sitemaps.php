<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure;

use Phil\Sitemap\Sitemap;
use Phil\Sitemap\UrlProvider;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the sitemap of the site: every provider adds its pages, then the `PopulateSitemap` listeners.
 */
final readonly class Sitemaps
{
    /**
     * @param iterable<UrlProvider> $providers
     */
    public function __construct(
        private iterable $providers,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * @param ?string $section the one section built, or null for all of them
     */
    public function build(?string $section = null): Sitemap
    {
        $sitemap = new Sitemap($section);

        foreach ($this->providers as $provider) {
            $provider->provide($sitemap, $section);
        }

        $this->dispatcher?->dispatch(new PopulateSitemap($sitemap, $section));

        return $sitemap;
    }
}
