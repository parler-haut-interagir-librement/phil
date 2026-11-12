<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Domain\Transition;

enum TransitionStatus: string
{
    /** Waiting for its time, or for another attempt. */
    case Pending = 'pending';

    /** Applied. */
    case Done = 'done';

    /** Every attempt failed. */
    case Failed = 'failed';

    /** Taken back before its time. */
    case Cancelled = 'cancelled';
}
