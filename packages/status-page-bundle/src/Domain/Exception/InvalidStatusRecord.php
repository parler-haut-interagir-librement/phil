<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A component, an incident or a maintenance that cannot be: its message names the field and what it must be, never
 * the value it was given.
 */
final class InvalidStatusRecord extends InvalidArgumentException implements StatusPageException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
