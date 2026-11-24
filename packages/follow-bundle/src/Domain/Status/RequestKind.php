<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Status;

/**
 * What a request asks the approver to accept: that the requester follows a subject they approve, or a mutual
 * relationship between the two members.
 */
enum RequestKind: string
{
    case Follow = 'follow';

    case Mutual = 'mutual';
}
