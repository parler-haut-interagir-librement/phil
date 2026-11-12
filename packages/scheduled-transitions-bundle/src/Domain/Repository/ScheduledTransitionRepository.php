<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;

interface ScheduledTransitionRepository
{
    public function save(ScheduledTransition ...$transitions): void;

    /**
     * The pending transitions of a resource, soonest first.
     *
     * @return list<ScheduledTransition>
     */
    public function pendingFor(ResourceReference $resource): array;

    /**
     * The pending transitions whose moment has come, and that no run has claimed — or whose claim has lapsed —, oldest
     * first.
     *
     * @return list<ScheduledTransition>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * Claims a due transition for one run until a moment, in one statement: `false` when another run holds it, or it
     * is no longer pending.
     */
    public function claim(ScheduledTransition $transition, DateTimeImmutable $now, DateTimeImmutable $until): bool;

    /**
     * Saves the outcome of an attempt and lets go of the claim, even when a failed flush of the handler's has made the
     * store unable to save entities.
     */
    public function record(ScheduledTransition $transition): void;

    /**
     * Whether the store can still load and save entities: `false` once a failed flush has closed it, until the next
     * run.
     */
    public function isUsable(): bool;
}
