<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Sitemap;

use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;

/**
 * The pages of the sitemap the contents make, one section per kind mounted on a route: every content each served
 * locale publishes itself, at its absolute URL, with when it was last published and the same content in the other
 * locales as `hreflang` alternates. A content whose SEO hides it from sitemaps or from indexing is left out, and so is
 * one without a URL. The section is the kind's key, `news_2026` written `news-2026` (`Sitemap::sectionNamed()`).
 *
 * Each locale is read in chunks (`PublishedContents::chunks()`), and the URLs of a chunk in one lookup of routes, so
 * the queries grow with the number of chunks, not of contents. What is kept across the locales, to name the
 * alternates, is each page's URL and date only.
 */
final readonly class ContentUrlProvider implements UrlProvider
{
    public function __construct(
        private DeclaredLocales $locales,
        private ResourceRoutes $resourceRoutes,
        private PublishedContents $published,
        private ContentUrlGenerator $urls,
    ) {
    }

    public function provide(UrlContainer $urls, ?string $section): void
    {
        foreach ($this->resourceRoutes->resourceKeys() as $resourceKey) {
            $kind = Sitemap::sectionNamed($resourceKey);

            if (null !== $section && $section !== $kind) {
                continue;
            }

            $pages = [];

            foreach ($this->locales->all() as $locale) {
                foreach ($this->published->chunks($resourceKey, $locale) as $chunk) {
                    $listed = array_filter($chunk, static fn (PublishedContent $content): bool => !$content->isHiddenFromSitemaps());
                    $addresses = $this->urls->generateAll(array_map(static fn (PublishedContent $content): ContentReference => $content->reference, $listed), $locale, true);

                    foreach ($listed as $content) {
                        $reference = $content->reference->toString();

                        if (isset($addresses[$reference])) {
                            $pages[$reference][$locale->value] = [$addresses[$reference], $content->publishedAt()];
                        }
                    }
                }
            }

            foreach ($pages as $translations) {
                $alternates = [];

                foreach ($translations as $locale => [$url]) {
                    if (\count($translations) > 1) {
                        $alternates[] = new Alternate($locale, $url);
                    }
                }

                foreach ($translations as [$url, $publishedAt]) {
                    $urls->add(new Url($url, $publishedAt, alternates: $alternates), $kind);
                }
            }
        }
    }
}
