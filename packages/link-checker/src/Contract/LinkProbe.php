<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Contract;

use Phil\LinkChecker\Probe\ProbeResult;
use Phil\Url\Url;

/**
 * Checks one address, without its fragment. `HttpLinkProbe` asks the address itself, through phil/safe-http.
 *
 * With `$withBody`, the probe also returns the HTML of the page, so that the anchors of an internal link can be looked
 * for; it is only asked for the pages of the site itself, never for another site's.
 */
interface LinkProbe
{
    public function probe(Url $url, bool $withBody = false): ProbeResult;
}
