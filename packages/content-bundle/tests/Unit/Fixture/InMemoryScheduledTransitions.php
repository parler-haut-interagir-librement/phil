<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;

/**
 * The scheduled transitions in memory, enough for `Scheduler::schedule()`, `cancel()` and `pendingFor()`.
 */
final class InMemoryScheduledTransitions implements ScheduledTransitionRepository
{
    /**
     * @var list<ScheduledTransition>
     */
    private array $transitions = [];

    #[Override]
    public function save(ScheduledTransition ...$transitions): void
    {
        foreach ($transitions as $transition) {
            if (!\in_array($transition, $this->transitions, true)) {
                $this->transitions[] = $transition;
            }
        }
    }

    #[Override]
    public function pendingFor(ResourceReference $resource): array
    {
        $pending = array_values(array_filter($this->transitions, static fn (ScheduledTransition $transition): bool => TransitionStatus::Pending === $transition->status() && $transition->resource()->equals($resource)));
        usort($pending, static fn (ScheduledTransition $a, ScheduledTransition $b): int => $a->dueAt() <=> $b->dueAt());

        return $pending;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return [];
    }

    #[Override]
    public function claim(ScheduledTransition $transition, DateTimeImmutable $now, DateTimeImmutable $until): bool
    {
        return false;
    }

    #[Override]
    public function record(ScheduledTransition $transition): void
    {
    }

    #[Override]
    public function isUsable(): bool
    {
        return true;
    }
}
