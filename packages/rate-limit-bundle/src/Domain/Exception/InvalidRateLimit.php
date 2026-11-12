<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A rate limit written wrong: an empty limiter name, an amount below one, a key strategy that is empty, or a route
 * default `_rate_limit` of a shape the bundle does not read.
 */
final class InvalidRateLimit extends InvalidArgumentException implements RateLimitException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
