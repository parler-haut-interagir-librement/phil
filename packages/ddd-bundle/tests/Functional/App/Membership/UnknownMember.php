<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use RuntimeException;

/**
 * Not a domain error: the query bus lets whatever its handler threw come out as is.
 */
final class UnknownMember extends RuntimeException
{
    public static function for(MemberId $id): self
    {
        return new self(\sprintf('No member "%s".', $id->toString()));
    }
}
