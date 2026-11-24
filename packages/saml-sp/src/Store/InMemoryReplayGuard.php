<?php

declare(strict_types=1);

namespace Phil\SamlSp\Store;

use DateTimeImmutable;
use Override;
use Phil\SamlSp\Contract\ReplayGuard;
use Psr\Clock\ClockInterface;

/**
 * Remembers the assertions in the memory of the process, until they expire: for tests and for a single process. A
 * site served by several workers or servers uses `CacheReplayGuard` on a shared cache.
 */
final class InMemoryReplayGuard implements ReplayGuard
{
    /** @var array<string, DateTimeImmutable> */
    private array $seen = [];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    #[Override]
    public function firstUse(string $issuer, string $assertionId, DateTimeImmutable $until): bool
    {
        $now = $this->clock->now();
        $this->seen = array_filter($this->seen, static fn (DateTimeImmutable $expiry): bool => $expiry > $now);
        $key = \strlen($issuer).':'.$issuer.$assertionId;

        if (isset($this->seen[$key])) {
            return false;
        }

        $this->seen[$key] = $until;

        return true;
    }
}
