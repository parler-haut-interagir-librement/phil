<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

/**
 * A span of work during which `BlameListener` writes no name: an erasure, whose flushes must not put the name of the
 * person erased — or of whoever runs it — back in `created_by` or `updated_by`. Spans nest; the listener writes again
 * once the outermost one ends, whether its work returned or threw. `reset()` ends every span, between the messages of
 * a worker.
 */
final class BlameSuspension
{
    private int $depth = 0;

    /**
     * Runs the work with the blame suspended, and gives back what it returns.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function during(callable $work): mixed
    {
        ++$this->depth;

        try {
            return $work();
        } finally {
            $this->depth = max(0, $this->depth - 1);
        }
    }

    public function isSuspended(): bool
    {
        return $this->depth > 0;
    }

    public function reset(): void
    {
        $this->depth = 0;
    }
}
