<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Domain\Exception\NoHandler;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Transitions planned for a moment: scheduled — or moved, when the same one is already pending —, cancelled, and
 * applied by their handler once due, each failed attempt retried at the next run until the attempts are spent.
 *
 * A run claims each transition before applying it, for `$claimFor` seconds: two runs at once — a slow cron run and the
 * next, cron and a worker — never apply the same transition twice. The outcome is saved even when the handler's own
 * failed flush has closed the entity manager; the run then stops, and the next one takes the rest.
 */
final readonly class Scheduler
{
    /**
     * @param iterable<TransitionHandler> $handlers
     */
    public function __construct(
        private ScheduledTransitionRepository $transitions,
        private iterable $handlers,
        private ClockInterface $clock,
        private int $maxAttempts = 3,
        private int $claimFor = 600,
    ) {
    }

    public function schedule(ResourceReference $resource, string $transition, DateTimeImmutable $at): ScheduledTransition
    {
        foreach ($this->transitions->pendingFor($resource) as $pending) {
            if ($pending->transition() === $transition) {
                $pending->reschedule($at);
                $this->transitions->save($pending);

                return $pending;
            }
        }

        $scheduled = ScheduledTransition::of($resource, $transition, $at);
        $this->transitions->save($scheduled);

        return $scheduled;
    }

    /**
     * Cancels a resource's pending transitions, or one of them; returns how many.
     */
    public function cancel(ResourceReference $resource, ?string $transition = null): int
    {
        $cancelled = array_filter($this->transitions->pendingFor($resource), static fn (ScheduledTransition $pending): bool => null === $transition || $pending->transition() === $transition);

        foreach ($cancelled as $pending) {
            $pending->cancel();
        }

        $this->transitions->save(...$cancelled);

        return \count($cancelled);
    }

    /**
     * @return list<ScheduledTransition>
     */
    public function pendingFor(ResourceReference $resource): array
    {
        return $this->transitions->pendingFor($resource);
    }

    /**
     * Applies the transitions that are due, at most so many.
     */
    public function runDue(int $limit = 100): RunReport
    {
        $now = $this->clock->now();
        [$applied, $failed] = [0, 0];

        foreach ($this->transitions->due($now, $limit) as $due) {
            // The claim runs from the moment it is made, not from the start of a run that may have lasted.
            $claimedAt = $this->clock->now();

            if (!$this->transitions->claim($due, $claimedAt, $claimedAt->modify(\sprintf('+%d seconds', $this->claimFor)))) {
                continue;
            }

            try {
                $this->handlerFor($due)->apply($due->resource(), $due->transition());
                $due->applied($now);
                ++$applied;
            } catch (Throwable $throwable) {
                $due->failed($throwable->getMessage(), $this->maxAttempts);
                ++$failed;
            }

            $this->transitions->record($due);

            if (!$this->transitions->isUsable()) {
                break;
            }
        }

        return new RunReport($applied, $failed);
    }

    private function handlerFor(ScheduledTransition $due): TransitionHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($due->resource(), $due->transition())) {
                return $handler;
            }
        }

        throw NoHandler::for($due->resource()->toString(), $due->transition());
    }
}
