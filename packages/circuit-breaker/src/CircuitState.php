<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

enum CircuitState: string
{
    /** Calls pass, and their outcomes are counted in the current window. */
    case Closed = 'closed';

    /** Calls go straight to the fallback, without waiting for a timeout, until the open duration elapses. */
    case Open = 'open';

    /** Trial calls pass: enough successes close the circuit, a single failure opens it again. */
    case HalfOpen = 'half_open';
}
