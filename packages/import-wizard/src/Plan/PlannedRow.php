<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

use DateTimeImmutable;

/**
 * A row the import writes: its physical line, whether it creates or updates, its natural key, the values of the columns
 * the file maps — null for an empty cell, so that an update clears it —, the line of the row of the same file each
 * reference points to, written before it, and its cells as written, for the rejected-rows file should the target refuse it.
 */
final readonly class PlannedRow
{
    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values    by column, the columns the file maps only
     * @param array<string, int>                                    $dependsOn the line of the row each reference column points to
     * @param list<string>                                          $cells
     */
    public function __construct(
        public int $line,
        public RowAction $action,
        public NaturalKey $key,
        public array $values,
        public array $dependsOn,
        public array $cells,
    ) {
    }

    /**
     * Whether the file maps the column: an update leaves the columns it does not map as they are.
     */
    public function has(string $column): bool
    {
        return \array_key_exists($column, $this->values);
    }

    public function value(string $column): string|int|bool|DateTimeImmutable|null
    {
        return $this->values[$column] ?? null;
    }
}
