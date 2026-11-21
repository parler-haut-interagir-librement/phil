<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

/**
 * Where one module's part of an erasure request stands.
 */
enum StepState: string
{
    /** Waiting for its message: recorded with the request, given back after a failed attempt, or reopened by a replay. */
    case Pending = 'pending';

    /** Leased to a worker that runs the module's eraser, or sent to an external service that has not acknowledged it. */
    case Running = 'running';

    /** The module erased the person, and its report is kept. */
    case Completed = 'completed';

    /** The module could not finish on its own: its retries ran out, or it cannot run any more. An operator looks at it. */
    case NeedsAttention = 'needs_attention';
}
