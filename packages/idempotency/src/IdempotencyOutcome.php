<?php

declare(strict_types=1);

namespace Phil\Idempotency;

/**
 * What to do with an operation that carries a key.
 */
enum IdempotencyOutcome
{
    /** The key is new in its scope, or its last run failed with the same payload: run, then store the answer. */
    case Proceed;

    /** Same key, same payload, first run completed: return the stored response, run nothing. */
    case Replay;

    /** Same key, another payload: `422`. Returning the first result would silently drop a corrected request. */
    case Reused;

    /** Same key, same payload, first run still in progress: `409` with `Retry-After`, and the caller retries later. */
    case InProgress;
}
