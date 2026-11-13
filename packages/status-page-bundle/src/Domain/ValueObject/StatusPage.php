<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;

/**
 * The page as it stands at one moment: its title, each component in its state, in the order the application declared
 * them, the incidents going on and those resolved within the history, newest first, and the maintenances in progress
 * and upcoming, soonest first. The service is as well as its worst component, and operational without any.
 */
final readonly class StatusPage
{
    /**
     * @param list<ComponentState>       $components
     * @param list<Incident>             $activeIncidents
     * @param list<ScheduledMaintenance> $maintenancesInProgress
     * @param list<ScheduledMaintenance> $upcomingMaintenances
     * @param list<Incident>             $history
     */
    public function __construct(
        public string $title,
        public DateTimeImmutable $generatedAt,
        public array $components,
        public array $activeIncidents,
        public array $maintenancesInProgress,
        public array $upcomingMaintenances,
        public array $history,
    ) {
    }

    public function status(): ComponentStatus
    {
        $status = ComponentStatus::Operational;

        foreach ($this->components as $state) {
            $status = $status->worst($state->status);
        }

        return $status;
    }

    /**
     * The components by group, in the order their first member was declared; components without a group under the
     * empty string.
     *
     * @return array<string, list<ComponentState>>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->components as $state) {
            $groups[$state->component->group ?? ''][] = $state;
        }

        return $groups;
    }

    /**
     * The name of the component with this id, or the id itself when the page has no such component.
     */
    public function componentName(string $id): string
    {
        foreach ($this->components as $state) {
            if ($state->component->id === $id) {
                return $state->component->name;
            }
        }

        return $id;
    }
}
