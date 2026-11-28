<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * Where a run stands.
 */
enum RunStatus: string
{
    /** Confirmed, waiting for its first chunk. */
    case Pending = 'pending';

    /** Some chunks done, others to come. */
    case Running = 'running';

    /** Every item handled; the report says how. */
    case Finished = 'finished';

    /** Ended early: the person lost the right to run the action, or the action left the configuration. */
    case Stopped = 'stopped';

    public function isOver(): bool
    {
        return self::Finished === $this || self::Stopped === $this;
    }
}
