<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use BackedEnum;
use Closure;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\ColumnType;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Reads the values of a row, an array by its key or an object through its accessors.
 */
final readonly class RowValues
{
    public function __construct(
        private PropertyAccessorInterface $accessor,
    ) {
    }

    /**
     * @param array<string, mixed>|object $row
     */
    public function read(array|object $row, string $path): mixed
    {
        return \is_array($row) ? $row[$path] ?? null : $this->accessor->getValue($row, $path);
    }

    /**
     * The value of a column; a block reads what it wants from the row, and has none unless one was asked for.
     *
     * @param array<string, mixed>|object $row
     */
    public function value(array|object $row, Column $column): mixed
    {
        if ($column->reader instanceof Closure) {
            return ($column->reader)($row);
        }

        return ColumnType::Block === $column->type && null === $column->property ? null : $this->read($row, $column->path());
    }

    /**
     * A value as a string, for the identifier and the version of a row and the text of a cell; empty when it has none.
     */
    public function string(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            \is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }
}
