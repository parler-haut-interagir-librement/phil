<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Contract;

use Phil\Publishing\PagePublished;
use Phil\SeoCheck\RenderedPage;
use Phil\Url\Url;

/**
 * Where the check gets the HTML of a page, as the application renders it, without a request over the network: a
 * sub-request to the kernel, a render of the page's template, a page cache.
 */
interface RenderedPages
{
    /**
     * The renderings of a page just published: the page at its canonical address first, then the other paths that lead
     * to it and the renderings for each channel the application serves it in. None when the page cannot be rendered.
     *
     * @return iterable<RenderedPage>
     */
    public function renderingsOf(PagePublished $event): iterable;

    /**
     * The rendering of another page of the application, as the `hreflang` check reads the translations of a page; null
     * when the address is not one of the application's pages.
     */
    public function renderingAt(Url $url): ?RenderedPage;
}
