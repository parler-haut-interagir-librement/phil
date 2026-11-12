<?php

declare(strict_types=1);

namespace Phil\DataTableExport;

use BackedEnum;
use DateTimeInterface;
use Phil\DataTableExport\Exception\ExportRefused;
use Stringable;

/**
 * What the writers share of a cell: its value brought to a few kinds, and the date written as text.
 *
 * @internal
 */
final class Cells
{
    /**
     * A backed enum as its value, a `Stringable` as its string; the other kinds as they are.
     *
     * @throws ExportRefused when the cell is of no kind a sheet holds
     */
    public static function value(mixed $cell): bool|int|float|string|DateTimeInterface|null
    {
        return match (true) {
            null === $cell, \is_bool($cell), \is_int($cell), \is_float($cell), \is_string($cell), $cell instanceof DateTimeInterface => $cell,
            $cell instanceof BackedEnum => $cell->value,
            $cell instanceof Stringable => (string) $cell,
            default => throw ExportRefused::because(\sprintf('A cell holds a %s, which no sheet writes: give a scalar, a date, a backed enum or a Stringable.', get_debug_type($cell))),
        };
    }

    /**
     * `2026-11-12` at midnight, `2026-11-12 14:30:00` otherwise, in the date's own zone.
     */
    public static function date(DateTimeInterface $date): string
    {
        return '00:00:00' === $date->format('H:i:s') ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
    }
}
