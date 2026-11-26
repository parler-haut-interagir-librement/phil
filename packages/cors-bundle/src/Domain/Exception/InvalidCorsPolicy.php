<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A CORS rule that could not be honoured safely or at all: a wildcard with credentials, an origin that is not an origin,
 * a pattern that does not compile or is not anchored, a method or header that is not a token. The bundle raises it while
 * the container compiles, so a wrong rule never reaches a request.
 */
final class InvalidCorsPolicy extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
