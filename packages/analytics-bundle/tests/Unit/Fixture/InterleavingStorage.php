<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * A storage that runs a second request once, right after the first has read its state and before it writes it back:
 * the interleaving of two requests of one key on two workers.
 */
final class InterleavingStorage implements StorageInterface
{
    private readonly InMemoryStorage $states;

    private ?Closure $between = null;

    public function __construct()
    {
        $this->states = new InMemoryStorage();
    }

    public function between(Closure $between): void
    {
        $this->between = $between;
    }

    #[Override]
    public function save(LimiterStateInterface $limiterState): void
    {
        $this->states->save($limiterState);
    }

    #[Override]
    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $state = $this->states->fetch($limiterStateId);
        $between = $this->between;
        $this->between = null;

        if ($between instanceof Closure) {
            $between();
        }

        return $state;
    }

    #[Override]
    public function delete(string $limiterStateId): void
    {
        $this->states->delete($limiterStateId);
    }
}
