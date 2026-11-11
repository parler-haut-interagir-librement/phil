<?php

declare(strict_types=1);

namespace Phil\Passwords\Exception;

use RuntimeException;
use Throwable;

/**
 * A breach source that could not answer: the network failed, the service answered with an error or too slowly, or its
 * answer was malformed or too large. It is the failure a circuit breaker counts; the password is never in its message.
 */
final class BreachCheckUnavailable extends RuntimeException implements PasswordsException
{
    public static function transport(Throwable $previous): self
    {
        return new self('The breach source could not be reached.', 0, $previous);
    }

    public static function status(int $status): self
    {
        return new self(\sprintf('The breach source answered with the HTTP status %d.', $status));
    }

    public static function malformed(): self
    {
        return new self('The breach source answered with a malformed range.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(\sprintf('The breach source answered with more than %d bytes.', $maxBytes));
    }
}
