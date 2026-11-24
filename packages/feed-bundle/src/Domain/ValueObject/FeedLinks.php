<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\Url\Url;

/**
 * The addresses a written feed names: its own (`self`), the WebSub hubs that announce it (`hub`), and, in a paged
 * feed (RFC 5005), its first page and the pages before and after it (`first`, `previous`, `next`).
 *
 * The first page names the feed: Atom writes it as the identifier of every page, so that a reader knows the pages
 * belong to one feed.
 */
final readonly class FeedLinks
{
    /**
     * @param list<Url> $hubs
     */
    public function __construct(
        public Url $self,
        public array $hubs = [],
        public ?Url $first = null,
        public ?Url $previous = null,
        public ?Url $next = null,
    ) {
    }

    /**
     * The address that names the feed: its first page, else its own.
     */
    public function feedId(): Url
    {
        return $this->first ?? $this->self;
    }
}
