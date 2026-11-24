<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

/**
 * One indexing directive of a page: its name in lower case — `noindex`, `nofollow`, `max-snippet` —, the crawler it is
 * addressed to, null for every crawler, and where it was read, `meta` or `header`.
 */
final readonly class RobotsDirective
{
    public const string META = 'meta';

    public const string HEADER = 'header';

    public function __construct(
        public string $name,
        public ?string $agent,
        public string $source,
    ) {
    }

    /**
     * Whether the directive keeps the page out of the index: `noindex`, or `none`, which also means `nofollow`.
     */
    public function forbidsIndexing(): bool
    {
        return 'noindex' === $this->name || 'none' === $this->name;
    }
}
