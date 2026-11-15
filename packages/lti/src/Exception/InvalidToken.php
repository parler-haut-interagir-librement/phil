<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;
use Throwable;

/**
 * A JSON Web Token, or one of its claims, that is malformed, unsigned by the expected key, expired, replayed or
 * addressed to someone else. The message says which check failed; it never repeats the token.
 */
final class InvalidToken extends RuntimeException implements LtiException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
