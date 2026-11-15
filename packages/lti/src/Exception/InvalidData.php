<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;
use Throwable;

/**
 * A document of a service — a line item, a score, a result, a membership page, a content item — whose shape or values
 * the specification does not allow.
 */
final class InvalidData extends RuntimeException implements LtiException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
