<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Support;

use Phil\AudienceSegments\Contract\Audience;

/**
 * An audience in memory, sorted as the port demands, that counts the pages asked.
 */
final class MemoryAudience implements Audience
{
    /** @var list<string> */
    private array $members;

    public int $pages = 0;

    public function __construct(string ...$members)
    {
        $this->members = array_values($members);
        sort($this->members, \SORT_STRING);
    }

    public function members(?string $after, int $limit): array
    {
        ++$this->pages;
        $after ??= '';

        return \array_slice(array_values(array_filter($this->members, static fn (string $m): bool => strcmp($m, $after) > 0)), 0, $limit);
    }
}
