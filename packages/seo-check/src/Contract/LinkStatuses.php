<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Contract;

use Phil\Url\Url;

/**
 * What the application knows of the links of a page: the status code each answered with, as a link checker last saw
 * it. The check never requests a link itself.
 */
interface LinkStatuses
{
    /**
     * The status code of each link known, by its address written as `Url::toString()` gives it; a link not known is left
     * out and not reported.
     *
     * @param list<Url> $links the links of the page, each once, without fragment
     *
     * @return array<string, int>
     */
    public function statuses(array $links): array;
}
