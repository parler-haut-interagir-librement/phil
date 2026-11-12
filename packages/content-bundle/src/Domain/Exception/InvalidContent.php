<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use InvalidArgumentException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A capability value refused its own construction: a blank title, a URL that is not absolute, a malformed
 * slug, a link that names neither a content nor a URL.
 *
 * Like `InvalidDimension`, the value must not exist, so the failure is an exception rather than a list of
 * violations. It is a `DomainError`, so the application catches it at its boundary without knowing the bundle,
 * and turns it into a message for whoever submitted the value.
 */
final class InvalidContent extends InvalidArgumentException implements DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
