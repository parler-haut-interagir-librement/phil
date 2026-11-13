<?php

declare(strict_types=1);

namespace Phil\Automations\Run;

use DateTimeImmutable;
use Override;
use Phil\Automations\Contract\RunStore;

/**
 * The runs in memory, for tests and for a process that runs its automations to the end at once. It is lost when the
 * process ends; a worker uses `DbalRunStore`.
 */
final class InMemoryRunStore implements RunStore
{
    /** @var array<string, AutomationRun> */
    private array $runs = [];

    #[Override]
    public function create(AutomationRun $run): bool
    {
        if (isset($this->runs[$run->id->value])) {
            return false;
        }

        $this->runs[$run->id->value] = $run;

        return true;
    }

    #[Override]
    public function get(RunId $id): ?AutomationRun
    {
        return $this->runs[$id->value] ?? null;
    }

    #[Override]
    public function save(AutomationRun $run): bool
    {
        $stored = $this->runs[$run->id->value] ?? null;

        if (!$stored instanceof AutomationRun || $stored->revision !== $run->revision) {
            return false;
        }

        $this->runs[$run->id->value] = $run->saved();

        return true;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->runs, static fn (AutomationRun $run): bool => $run->isDue($now));
        uasort($due, static fn (AutomationRun $a, AutomationRun $b): int => ($a->resumeAt?->getTimestamp() ?? 0) <=> ($b->resumeAt?->getTimestamp() ?? 0));

        return array_values(array_map(static fn (AutomationRun $run): RunId => $run->id, \array_slice($due, 0, max(1, $limit))));
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $count = \count($this->runs);
        $this->runs = array_filter($this->runs, static fn (AutomationRun $run): bool => !$run->state->isFinished() || $run->updatedAt->getTimestamp() >= $before->getTimestamp());

        return $count - \count($this->runs);
    }
}
