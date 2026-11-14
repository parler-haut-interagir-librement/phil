<?php

declare(strict_types=1);

namespace Phil\FormLogic\Exception;

use InvalidArgumentException;

/**
 * Logic refused: a JsonLogic document outside the subset or over the limits, an expression built against its rules, or
 * a condition that reads a variable it may not.
 */
final class InvalidLogic extends InvalidArgumentException implements FormLogicException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
