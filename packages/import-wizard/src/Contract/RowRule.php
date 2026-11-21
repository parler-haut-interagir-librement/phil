<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Contract;

use DateTimeImmutable;
use Phil\ImportWizard\Plan\CellError;

/**
 * A check across the cells of one row, run once every cell has been read without error. A rule is serialised with
 * its profile: `name()` identifies its class when the profile is read back.
 */
interface RowRule
{
    /**
     * The name the profile writes beside the rule's fields.
     */
    public static function name(): string;

    /**
     * @param array<array-key, mixed> $data the fields `toArray()` wrote
     */
    public static function fromArray(array $data): static;

    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values the row's values by column, null for an empty cell
     *
     * @return list<CellError>
     */
    public function check(array $values): array;

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array;
}
