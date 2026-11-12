<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

/**
 * The beginning of a page fetched from another site: its HTML up to the bound read, the address it was served from
 * after its redirects, and the character set its server declared.
 */
final readonly class FetchedPage
{
    public function __construct(
        public string $html,
        public string $url,
        public ?string $charset = null,
    ) {
    }
}
