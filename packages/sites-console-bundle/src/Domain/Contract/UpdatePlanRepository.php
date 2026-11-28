<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;

/**
 * The update plans and their steps.
 */
interface UpdatePlanRepository
{
    public function get(string $id): ?UpdatePlan;

    public function save(UpdatePlan $plan): void;

    /**
     * @return list<UpdatePlan> the newest first
     */
    public function recent(int $limit): array;
}
