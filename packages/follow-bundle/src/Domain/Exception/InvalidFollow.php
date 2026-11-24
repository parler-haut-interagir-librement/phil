<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value that cannot make a follow, a request, an activity or a preference: an identifier of the wrong shape, an
 * unknown level, too many entries. The message never repeats the value, which may be a name or an address.
 */
final class InvalidFollow extends InvalidArgumentException implements FollowException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
