<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Check;

use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A PSR-6 cache pool keeps a value: a probe under a key of its own is saved, read back and deleted. On a pool over
 * Valkey or Redis, it checks the server answers and accepts writes.
 */
final readonly class CachePoolCheck implements HealthCheck
{
    public const string KEY_PREFIX = 'phil_monitoring.probe.';

    public function __construct(
        private CacheItemPoolInterface $pool,
        private string $name,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $token = (string) random_int(\PHP_INT_MIN, \PHP_INT_MAX);
        $key = self::KEY_PREFIX.$token;

        if (!$this->pool->save($this->pool->getItem($key)->set($token)->expiresAfter(60))) {
            return CheckOutcome::down('Refuses to save a value.');
        }

        $kept = $this->pool->getItem($key)->get();
        $this->pool->deleteItem($key);

        return $token === $kept ? CheckOutcome::up('Keeps a value.') : CheckOutcome::down('Loses the value it saved.');
    }
}
