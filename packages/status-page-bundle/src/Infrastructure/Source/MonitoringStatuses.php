<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Source;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The state of each component from the surveillance of `phil/monitoring-bundle`: the worst of the checks it names —
 * up is operational, degraded is degraded, down is a major outage —, degraded while a scheduled task it names is late,
 * and under maintenance, every component, while the site's maintenance mode is on. A component that names no check
 * nor task, or only ones the surveillance does not know, keeps its declared state.
 *
 * The checks run once per `$ttl` seconds at most: the states are kept in the cache pool, as plain values, under one
 * key; with a ttl of 0 or no pool, they run on every page.
 */
final readonly class MonitoringStatuses implements ComponentStatuses
{
    public const string CACHE_KEY = 'phil_status_page.monitoring_statuses';

    /**
     * @param array<string, list<string>> $checks the names of the checks of each component, by component id
     * @param array<string, list<string>> $tasks  the names of the scheduled tasks of each component, by component id
     */
    public function __construct(
        private MonitoringStatus $monitoring,
        private DeclaredStatuses $declared,
        private array $checks,
        private array $tasks,
        private ?CacheInterface $cache = null,
        private int $ttl = 60,
    ) {
    }

    #[Override]
    public function statuses(array $components): array
    {
        $values = !$this->cache instanceof CacheInterface || 0 === $this->ttl
            ? $this->values($components)
            : $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($components): array {
                $item->expiresAfter($this->ttl);

                return $this->values($components);
            });

        /** @var array<string, string> $values */
        return array_map(ComponentStatus::from(...), $values);
    }

    /**
     * @param list<Component> $components
     *
     * @return array<string, string>
     */
    private function values(array $components): array
    {
        $declared = $this->declared->statuses($components);
        $maintenance = $this->monitoring->maintenance() instanceof MaintenanceState;
        $health = [];

        foreach ($this->monitoring->health()->results as $result) {
            $health[$result->name] = $result->status();
        }

        $late = [];

        foreach ($this->monitoring->heartbeats() as $heartbeat) {
            $late[$heartbeat->task] = HeartbeatState::Late === $heartbeat->state;
        }

        $values = [];

        foreach ($components as $component) {
            $status = $declared[$component->id] ?? ComponentStatus::Operational;
            $watched = false;
            $worst = HealthStatus::Up;

            foreach ($this->checks[$component->id] ?? [] as $check) {
                if (isset($health[$check])) {
                    $watched = true;
                    $worst = $worst->worst($health[$check]);
                }
            }

            foreach ($this->tasks[$component->id] ?? [] as $task) {
                if (isset($late[$task])) {
                    $watched = true;
                    $worst = $late[$task] ? $worst->worst(HealthStatus::Degraded) : $worst;
                }
            }

            if ($watched) {
                $status = match ($worst) {
                    HealthStatus::Up => ComponentStatus::Operational,
                    HealthStatus::Degraded => ComponentStatus::Degraded,
                    HealthStatus::Down => ComponentStatus::MajorOutage,
                };
            }

            $values[$component->id] = ($maintenance ? ComponentStatus::Maintenance : $status)->value;
        }

        return $values;
    }
}
