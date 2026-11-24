<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

/**
 * What asking to follow, or to join a mutual relationship, led to: done at once, or a request waiting for its approver.
 */
enum FollowOutcome: string
{
    case Done = 'done';

    case Requested = 'requested';
}
