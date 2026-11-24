<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Support;

use Override;
use Phil\SeoCheck\Contract\LinkStatuses;
use Phil\Url\Url;

/**
 * The statuses of links, kept in memory by address; it remembers the links it was asked about.
 */
final class MemoryLinkStatuses implements LinkStatuses
{
    /** @var list<list<string>> */
    public array $asked = [];

    /**
     * @param array<string, int> $statuses
     */
    public function __construct(private readonly array $statuses = [])
    {
    }

    #[Override]
    public function statuses(array $links): array
    {
        $this->asked[] = array_map(static fn (Url $url): string => $url->toString(), $links);

        return $this->statuses;
    }
}
