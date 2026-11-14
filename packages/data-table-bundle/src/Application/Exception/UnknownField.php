<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use LogicException;

/**
 * A sort or a filter names a field the data source did not list. Only the fields of its allow-list reach a query.
 */
final class UnknownField extends LogicException implements DataTableException
{
    public static function named(string $field): self
    {
        return new self(\sprintf('The field "%s" is not among the fields this data source lists.', $field));
    }
}
