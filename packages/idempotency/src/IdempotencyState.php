<?php

declare(strict_types=1);

namespace Phil\Idempotency;

/**
 * Where the operation of one key stands. The value is what the stores write.
 */
enum IdempotencyState: string
{
    /** Reserved and running. The reservation expires, so that a process killed in between does not keep the key. */
    case InProgress = 'in_progress';

    /** Answered: the stored response is replayed until the key's retention ends. */
    case Completed = 'completed';

    /** The run failed in a way the caller may retry: the same payload runs again, another payload is still refused. */
    case Failed = 'failed';
}
