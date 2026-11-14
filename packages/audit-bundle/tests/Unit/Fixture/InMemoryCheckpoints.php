<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Contract\FeedCheckpoints;

/**
 * The feeds' places in memory.
 */
final class InMemoryCheckpoints implements FeedCheckpoints
{
    /** @var array<string, int> */
    public array $places = [];

    #[Override]
    public function position(string $feed, string $segment): int
    {
        return max(0, $this->places[$feed.'|'.$segment] ?? 0);
    }

    #[Override]
    public function advance(string $feed, string $segment, int $sequence): void
    {
        $this->places[$feed.'|'.$segment] = max($this->position($feed, $segment), $sequence);
    }
}
