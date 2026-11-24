<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Support;

use Override;
use Phil\LinkChecker\Contract\InternalPages;
use Phil\Url\Url;

/**
 * The pages of a site held in memory, by address; it remembers the addresses it was asked for.
 */
final class MemoryInternalPages implements InternalPages
{
    /** @var list<string> */
    public array $asked = [];

    /**
     * @param array<string, string> $pages
     */
    public function __construct(
        private readonly array $pages,
    ) {
    }

    #[Override]
    public function html(Url $url): ?string
    {
        $this->asked[] = $url->toString();

        return $this->pages[$url->toString()] ?? null;
    }
}
