<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Functional\App;

use DateTimeImmutable;
use Phil\Sitemap\Image;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Three articles, in their own section, autoconfigured as a provider, with a priority of its own that still puts it
 * after the pages of the routes.
 */
#[AsTaggedItem(priority: 10)]
final readonly class ArticleProvider implements UrlProvider
{
    public function provide(UrlContainer $urls, ?string $section): void
    {
        if (null !== $section && 'articles' !== $section) {
            return;
        }

        foreach (['un', 'deux', 'trois'] as $slug) {
            $urls->add(new Url('https://example.com/articles/'.$slug, new DateTimeImmutable('2026-11-04 12:00 UTC'), images: [new Image('https://example.com/'.$slug.'.png')]), 'articles');
        }
    }
}
