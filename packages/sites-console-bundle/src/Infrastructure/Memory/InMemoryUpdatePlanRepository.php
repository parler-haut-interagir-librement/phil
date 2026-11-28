<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;

/**
 * The update plans in memory, for tests and demonstrations.
 */
final class InMemoryUpdatePlanRepository implements UpdatePlanRepository
{
    /** @var array<string, UpdatePlan> */
    private array $plans = [];

    #[Override]
    public function get(string $id): ?UpdatePlan
    {
        return $this->plans[$id] ?? null;
    }

    #[Override]
    public function save(UpdatePlan $plan): void
    {
        $this->plans[$plan->id()] = $plan;
    }

    #[Override]
    public function recent(int $limit): array
    {
        $plans = $this->plans;
        krsort($plans, \SORT_STRING);

        return \array_slice(array_values($plans), 0, max(1, $limit));
    }
}
