<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Functional\App;

use Phil\Sitemap\Url;
use Phil\SitemapBundle\Infrastructure\PopulateSitemap;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds a page as PopulateSitemap is dispatched, as a listener of the populate event does.
 */
final readonly class ContactListener
{
    #[AsEventListener]
    public function __invoke(PopulateSitemap $event): void
    {
        $event->urls->add(new Url('https://example.com/contact'), 'default');
    }
}
