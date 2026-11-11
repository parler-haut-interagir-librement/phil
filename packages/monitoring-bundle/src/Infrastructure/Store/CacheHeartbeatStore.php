<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Store;

use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The heartbeats in a PSR-6 cache pool, one key per task that never expires: `{"beat": "<ATOM>", "alerted": bool}`.
 * Give it a pool that does not evict — on a filesystem, or on Valkey or Redis without an eviction policy —: a lost
 * beat reads as a task that never ran, which is not alerted. An entry in another shape reads as no beat.
 */
final readonly class CacheHeartbeatStore implements HeartbeatStore
{
    public const string KEY_PREFIX = 'phil_monitoring.heartbeat.';

    private const string FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function lastBeat(string $task): ?DateTimeImmutable
    {
        $beat = $this->record($task)['beat'] ?? null;
        $at = \is_string($beat) ? DateTimeImmutable::createFromFormat(self::FORMAT, $beat) : false;

        return false === $at ? null : $at;
    }

    #[Override]
    public function beat(string $task, DateTimeImmutable $at): void
    {
        $this->save($task, ['beat' => $at->format(self::FORMAT), 'alerted' => false]);
    }

    #[Override]
    public function isAlerted(string $task): bool
    {
        return true === ($this->record($task)['alerted'] ?? null);
    }

    #[Override]
    public function markAlerted(string $task): void
    {
        $this->save($task, ['alerted' => true] + $this->record($task));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(string $task): array
    {
        $record = $this->pool->getItem($this->key($task))->get();

        return \is_array($record) ? $record : [];
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function save(string $task, array $record): void
    {
        $this->pool->save($this->pool->getItem($this->key($task))->set($record));
    }

    /**
     * A task name may hold a character PSR-6 reserves, such as the colon: the key holds its hash.
     */
    private function key(string $task): string
    {
        return self::KEY_PREFIX.hash('xxh128', $task);
    }
}
