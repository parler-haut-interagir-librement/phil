<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Phil\Publishing\PagePublished;
use Phil\SeoCheck\Contract\ChannelIndexing;
use Phil\SeoCheck\Contract\RenderedPages;
use Phil\SeoCheck\Markup\MarkupReader;

/**
 * Builds the page to check from the event that announced it: its canonical address, its language and its flag
 * « indexable » from `PagePublished`, its HTML from the application's `RenderedPages`, and, when the application serves
 * it in several channels, whether it is indexable in each from `ChannelIndexing`.
 */
final readonly class PageCheckFactory
{
    public function __construct(
        private RenderedPages $pages,
        private ?ChannelIndexing $channels = null,
        private MarkupReader $reader = new MarkupReader(),
    ) {
    }

    /**
     * The page to check, or null when the application renders none of it.
     */
    public function fromEvent(PagePublished $event): ?CheckedPage
    {
        $renderings = [];
        $indexableByChannel = [];
        $asked = [];

        foreach ($this->pages->renderingsOf($event) as $page) {
            $renderings[] = $page;
            $channel = $page->channel;

            if (null !== $channel && $this->channels instanceof ChannelIndexing && !isset($asked[$channel])) {
                $asked[$channel] = $channel;
                $indexable = $this->channels->isIndexable($event, $channel);

                if (null !== $indexable) {
                    $indexableByChannel[$channel] = $indexable;
                }
            }
        }

        if ([] === $renderings) {
            return null;
        }

        return CheckedPage::of($event->canonicalUrl, $event->locale, $event->indexable, $renderings, $indexableByChannel, $this->reader);
    }
}
