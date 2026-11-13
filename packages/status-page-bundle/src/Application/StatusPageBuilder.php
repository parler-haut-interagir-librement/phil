<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application;

use DateTimeImmutable;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;
use Psr\Clock\ClockInterface;

/**
 * Puts the page together at the clock's present: each component starts in the state its source gives — operational
 * when it gives none —, is under maintenance while a maintenance covers it, and takes the worst impact of the
 * incidents going on that name it. The history holds the incidents resolved within the last `$historyDays` days.
 * Nothing is kept between two pages.
 */
final readonly class StatusPageBuilder
{
    /**
     * @param list<Component> $components
     */
    public function __construct(
        private string $title,
        private array $components,
        private ComponentStatuses $statuses,
        private StatusRecords $records,
        private ClockInterface $clock,
        private int $historyDays = 90,
    ) {
    }

    public function build(): StatusPage
    {
        $now = $this->clock->now();
        $statuses = $this->statuses->statuses($this->components);
        $incidents = $this->records->incidents();
        $maintenances = $this->records->maintenances();

        $active = array_filter($incidents, static fn (Incident $incident): bool => $incident->isActiveAt($now));
        $since = $now->modify(\sprintf('-%d days', $this->historyDays));
        $history = array_filter($incidents, static fn (Incident $incident): bool => $incident->resolvedAt() instanceof DateTimeImmutable && $incident->resolvedAt() >= $since && $incident->startedAt() <= $now);
        $inProgress = array_filter($maintenances, static fn (ScheduledMaintenance $maintenance): bool => $maintenance->isInProgressAt($now));
        $upcoming = array_filter($maintenances, static fn (ScheduledMaintenance $maintenance): bool => $maintenance->isUpcomingAt($now));

        $newestFirst = static fn (Incident $a, Incident $b): int => $b->startedAt() <=> $a->startedAt();
        $soonestFirst = static fn (ScheduledMaintenance $a, ScheduledMaintenance $b): int => $a->startsAt <=> $b->startsAt;
        usort($active, $newestFirst);
        usort($history, $newestFirst);
        usort($inProgress, $soonestFirst);
        usort($upcoming, $soonestFirst);

        $states = [];

        foreach ($this->components as $component) {
            $states[] = new ComponentState($component, $this->status($component, $statuses[$component->id] ?? ComponentStatus::Operational, $active, $inProgress));
        }

        return new StatusPage($this->title, $now, $states, $active, $inProgress, $upcoming, $history);
    }

    /**
     * @param list<Incident>             $incidents
     * @param list<ScheduledMaintenance> $maintenances
     */
    private function status(Component $component, ComponentStatus $status, array $incidents, array $maintenances): ComponentStatus
    {
        foreach ($maintenances as $maintenance) {
            if ($maintenance->affects($component->id)) {
                $status = ComponentStatus::Maintenance;
            }
        }

        foreach ($incidents as $incident) {
            if ($incident->affects($component->id)) {
                $status = $status->worst($incident->impact);
            }
        }

        return $status;
    }
}
