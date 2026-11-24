<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

use Phil\Url\Url;

/**
 * One content whose links are checked: its identifier in the application, the address of the page that shows it,
 * against which its relative links are resolved, and its HTML, a whole page or a fragment.
 */
final readonly class ContentLinks
{
    public function __construct(
        public string $id,
        public Url $page,
        public string $html,
    ) {
    }
}
