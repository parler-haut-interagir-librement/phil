<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Render;

/**
 * The addresses the page links to: itself, its JSON and its Atom feed — paths of the site when it is served, names
 * beside it in a snapshot, absolute addresses in the feed.
 */
final readonly class PageLinks
{
    public function __construct(
        public string $page,
        public string $json,
        public string $atom,
    ) {
    }
}
