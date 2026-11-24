<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Probe;

use Phil\LinkChecker\LinkResult;

/**
 * What a probe found, and the HTML of the page when it was asked for and the page is HTML: `$complete` says whether
 * that HTML was read to its end.
 */
final readonly class ProbeResult
{
    public function __construct(
        public LinkResult $result,
        public ?string $html = null,
        public bool $complete = true,
    ) {
    }
}
