<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\InMemory;

use DateTimeImmutable;
use Override;
use Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bounce history in memory, for tests: it forgets everything at the end of the process, and on `reset()` between
 * the requests of a worker.
 */
final class InMemoryBounceHistoryRepository implements BounceHistoryRepository, ResetInterface
{
    /** @var array<string, int> the end of each claim, by key */
    private array $claims = [];

    /** @var array<string, list<int>> the times of the soft bounces, by subject */
    private array $softBounces = [];

    public function __construct(
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    #[Override]
    public function claim(string $key, DateTimeImmutable $until): bool
    {
        $now = $this->clock->now()->getTimestamp();

        if (($this->claims[$key] ?? $now) > $now) {
            return false;
        }

        $this->claims[$key] = $until->getTimestamp();

        return true;
    }

    #[Override]
    public function recordSoftBounce(string $subject, DateTimeImmutable $at, DateTimeImmutable $since): int
    {
        $start = $since->getTimestamp();
        // The spread numbers the times kept from 0 again.
        $this->softBounces[$subject] = [...array_filter($this->softBounces[$subject] ?? [], static fn (int $time): bool => $time >= $start), $at->getTimestamp()];

        return \count($this->softBounces[$subject]);
    }

    #[Override]
    public function forgetSoftBounces(string $subject): void
    {
        unset($this->softBounces[$subject]);
    }

    #[Override]
    public function reset(): void
    {
        $this->claims = [];
        $this->softBounces = [];
    }
}
