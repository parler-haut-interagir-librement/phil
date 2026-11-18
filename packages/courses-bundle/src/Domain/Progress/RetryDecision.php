<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Progress;

/**
 * Where a request for a second chance stands.
 */
enum RetryDecision: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Refused = 'refused';
    /** The activity was waived instead: the course counts it satisfied. */
    case Waived = 'waived';
}
