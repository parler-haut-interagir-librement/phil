<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Exception;

use Phil\ReactionsBundle\Domain\Exception\ReactionsException;
use RuntimeException;

/**
 * A reaction was not recorded: the access refuses it, or the request cannot be trusted.
 */
final class ReactionRefused extends RuntimeException implements ReactionsException
{
    public static function byAccess(string $code): self
    {
        return new self(\sprintf('The reaction "%s" is refused to this member.', $code));
    }

    public static function untrustedRequest(): self
    {
        return new self('The request does not come from a page of this site.');
    }

    public static function noMember(): self
    {
        return new self('Only a member can react.');
    }
}
