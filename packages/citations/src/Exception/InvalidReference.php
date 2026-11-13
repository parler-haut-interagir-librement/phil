<?php

declare(strict_types=1);

namespace Phil\Citations\Exception;

use InvalidArgumentException;

/**
 * A reference that breaks the model: no id, an unknown type or variable, a name without a family or a literal, a date
 * that is not one, CSL-JSON that is not a list of items, or two different references under one id.
 */
final class InvalidReference extends InvalidArgumentException implements CitationsException
{
    public static function because(string $problem): self
    {
        return new self(\sprintf('Invalid reference: %s.', $problem));
    }
}
