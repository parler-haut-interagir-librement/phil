<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application\Exception;

use InvalidArgumentException;
use Phil\EditLockBundle\Domain\Exception\EditLockException;
use Throwable;

/**
 * A saved block that `phil/ujo` does not accept, or a place in the document that cannot be one.
 */
final class InvalidBlock extends InvalidArgumentException implements EditLockException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
