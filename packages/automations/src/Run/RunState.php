<?php

declare(strict_types=1);

namespace Phil\Automations\Run;

/**
 * Where a run stands.
 */
enum RunState: string
{
    /** A worker holds it, until its lease ends. */
    case Running = 'running';

    /** It goes on at its time: a new run, a delay, a retry. */
    case Waiting = 'waiting';

    /** Every step ran. */
    case Completed = 'completed';

    /** A filter ended it, without failure. */
    case Filtered = 'filtered';

    /** A step failed; the steps before it that could be undone were. */
    case Failed = 'failed';

    /** A step failed, and undoing a step before it failed too: someone has to look. */
    case CompensationFailed = 'compensation_failed';

    public function isFinished(): bool
    {
        return !\in_array($this, [self::Running, self::Waiting], true);
    }
}
