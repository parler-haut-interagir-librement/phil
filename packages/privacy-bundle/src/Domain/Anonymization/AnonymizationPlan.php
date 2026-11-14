<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Declaration\PersonalField;

/**
 * The columns an anonymization rewrites, table by table, and the personal fields it leaves as they are.
 *
 * **A later rule replaces an earlier one on the same column.** The reader adds the rules of the attributes first,
 * those of the personal data categories next, and those of the configuration last, so the configuration has the
 * final word without touching the model.
 *
 * **A personal field that no rule reaches is reported, not guessed.** `uncovered` lists the `#[PersonalData]`
 * fields of mapped columns that neither an `#[Anonymize]`, a category nor the configuration covers: a copy made
 * from this plan still holds them in clear.
 */
final readonly class AnonymizationPlan
{
    /** @var array<non-empty-string, non-empty-array<non-empty-string, ColumnRule>> */
    private array $tables;

    /**
     * @param list<ColumnRule>    $rules
     * @param list<PersonalField> $uncovered
     */
    public function __construct(
        array $rules,
        public array $uncovered = [],
    ) {
        $tables = [];

        foreach ($rules as $rule) {
            $tables[$rule->table][$rule->column] = $rule;
        }

        ksort($tables);
        $this->tables = $tables;
    }

    /**
     * The rules grouped by table, tables in alphabetical order, columns in the order they were first given.
     *
     * @return array<non-empty-string, non-empty-list<ColumnRule>>
     */
    public function tables(): array
    {
        return array_map(array_values(...), $this->tables);
    }

    public function isEmpty(): bool
    {
        return [] === $this->tables;
    }
}
