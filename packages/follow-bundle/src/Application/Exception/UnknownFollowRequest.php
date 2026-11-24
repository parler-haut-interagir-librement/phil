<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Exception;

use Phil\FollowBundle\Domain\Exception\FollowException;
use RuntimeException;

/**
 * A request that does not exist, or that the member is not the approver of: both say the same, so that no one learns
 * of another member's requests.
 */
final class UnknownFollowRequest extends RuntimeException implements FollowException
{
    public static function create(): self
    {
        return new self('No such request waits for this member.');
    }
}
