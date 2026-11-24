<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Throttle;

use Override;
use Phil\LinkChecker\Contract\HostThrottle;
use Phil\LinkChecker\Exception\InvalidOption;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A pace per host, kept in memory: two requests to the same host are at least `$interval` seconds apart; requests to
 * different hosts do not wait for each other. The clock sleeps, so that a test gives a `MockClock` and waits no time.
 *
 * It remembers the last `$maxHosts` hosts, the least recent forgotten first, and forgets them all on `reset()`, which a
 * worker calls between messages. Several processes share no pace: an application that checks links in several workers
 * at once implements `HostThrottle` on a shared store, Symfony's RateLimiter with a lock for instance.
 */
final class IntervalThrottle implements HostThrottle, ResetInterface
{
    public const float MAX_INTERVAL = 60.0;

    public const int MAX_HOSTS = 100_000;

    /** The seconds between two requests to a host by default. */
    public const float DEFAULT_INTERVAL = 1.0;

    /** The hosts remembered by default. */
    public const int DEFAULT_HOSTS = 1_000;

    /** @var array<string, float> when the next request to each host may leave, in seconds */
    private array $next = [];

    public function __construct(
        private readonly float $interval = self::DEFAULT_INTERVAL,
        private readonly ClockInterface $clock = new NativeClock(),
        private readonly int $maxHosts = self::DEFAULT_HOSTS,
    ) {
        if ($interval < 0.0 || $interval > self::MAX_INTERVAL) {
            throw InvalidOption::outOfRange('The interval between two requests to a host', $interval, 0, self::MAX_INTERVAL);
        }

        if ($maxHosts < 1 || $maxHosts > self::MAX_HOSTS) {
            throw InvalidOption::outOfRange('The hosts remembered', $maxHosts, 1, self::MAX_HOSTS);
        }
    }

    #[Override]
    public function wait(string $host): void
    {
        $now = (float) $this->clock->now()->format('U.u');
        $start = max($now, $this->next[$host] ?? $now);
        // Sleeping no time costs nothing: the clock is asked to sleep whatever the delay.
        $this->clock->sleep($start - $now);

        // Written again at the end, the host becomes the most recent one.
        unset($this->next[$host]);
        $this->next[$host] = $start + $this->interval;

        if (\count($this->next) > $this->maxHosts) {
            unset($this->next[array_key_first($this->next)]);
        }
    }

    #[Override]
    public function reset(): void
    {
        $this->next = [];
    }
}
