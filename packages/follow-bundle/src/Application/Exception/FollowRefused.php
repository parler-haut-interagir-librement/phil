<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Exception;

use Phil\FollowBundle\Domain\Exception\FollowException;
use RuntimeException;

/**
 * A change the bundle does not make: following oneself, a subject the member may not follow, a mutual relationship
 * while they are turned off, a request from a browser that did not pass the checks.
 */
final class FollowRefused extends RuntimeException implements FollowException
{
    public static function self(): self
    {
        return new self('A member does not follow themselves.');
    }

    public static function noAccess(): self
    {
        return new self('The member may not follow this subject.');
    }

    public static function mutualDisabled(): self
    {
        return new self('Mutual relationships are turned off: set "phil_follow.mutual.enabled" to true.');
    }

    public static function noMember(): self
    {
        return new self('Following needs a member.');
    }

    public static function untrustedRequest(): self
    {
        return new self('The request did not pass the CSRF check.');
    }
}
