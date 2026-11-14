<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use InvalidArgumentException;

/**
 * What was typed in an editable cell cannot be read as its column's type.
 */
final class InvalidEditValue extends InvalidArgumentException implements DataTableException
{
    public static function forColumn(string $column): self
    {
        return new self(\sprintf('The value sent for the column "%s" cannot be read.', $column));
    }
}
