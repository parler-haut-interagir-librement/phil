<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * Where a session of the run-time API stands: before `Initialize()`, between it and `Terminate()`, or after.
 */
enum SessionState: string
{
    case NotInitialized = 'not-initialized';
    case Running = 'running';
    case Terminated = 'terminated';
}
