<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Contract;

use Phil\Url\Url;

/**
 * Gives the HTML of a page of the site itself without an HTTP request, when the application can render or read it:
 * the page of a content found by its route, say. The address comes without its fragment.
 *
 * Null means the application cannot tell: the page is then asked over HTTP. A page the application knows does not
 * exist returns null too, and the request finds it dead.
 */
interface InternalPages
{
    public function html(Url $url): ?string;
}
