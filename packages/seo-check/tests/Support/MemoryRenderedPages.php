<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Support;

use Override;
use Phil\Publishing\PagePublished;
use Phil\SeoCheck\Contract\RenderedPages;
use Phil\SeoCheck\RenderedPage;
use Phil\Url\Url;

/**
 * Renderings kept in memory: those of the published page, and the other pages by address. It remembers each address
 * asked for.
 */
final class MemoryRenderedPages implements RenderedPages
{
    /** @var list<string> */
    public array $asked = [];

    /**
     * @param list<RenderedPage> $renderings the renderings of the published page
     * @param list<RenderedPage> $others     the other pages of the application
     */
    public function __construct(
        private readonly array $renderings = [],
        private readonly array $others = [],
    ) {
    }

    #[Override]
    public function renderingsOf(PagePublished $event): iterable
    {
        yield from $this->renderings;
    }

    #[Override]
    public function renderingAt(Url $url): ?RenderedPage
    {
        $this->asked[] = $url->toString();

        foreach ($this->others as $page) {
            if ($page->url->equals($url)) {
                return $page;
            }
        }

        return null;
    }
}
