<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Exception;

use Phil\AccessBundle\Domain\Exception\AccessException;
use RuntimeException;

/**
 * "View as this member" refused: nobody is signed in, the member is no admin of the space, asks to view as
 * themselves or as someone who is no member of the space; or a change was asked for while an admin views the site as
 * a member, which only ever reads.
 */
final class ViewAsRefused extends RuntimeException implements AccessException
{
    public static function signedOut(): self
    {
        return new self('Sign in to view the site as a member.');
    }

    public static function notAnAdmin(): self
    {
        return new self('Only an admin of the space views the site as a member.');
    }

    public static function self(): self
    {
        return new self('A member views the site as someone else, not as themselves.');
    }

    public static function notAMemberOfTheSpace(): self
    {
        return new self('An admin views the site only as a member of the space they administer.');
    }

    public static function readOnly(): self
    {
        return new self('Viewing the site as a member only reads: stop viewing as them to make a change.');
    }
}
