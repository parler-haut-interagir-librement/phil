<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value a well-known document refuses: a security.txt field that is not a URI of its kind, a WebFinger resource that
 * is not one, a path outside the grammar of a well-known name, a DID that is not one.
 */
final class InvalidValue extends InvalidArgumentException implements WellKnownException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
