<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Contract;

use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\ValueObject\Field;

/**
 * Reads the conditions of a form: when a field is asked, when a notification is sent. A condition is data, an array
 * whose serialization is a subset of JsonLogic, such as `{"in": [{"var": "team"}, ["Blue", "Green"]]}`; the
 * evaluator says which fields it reads, checks it against their types when the form is defined, and tells whether it
 * holds for the values of an entry. `BasicConditions` is the bundle's own; an evaluator of richer logic implements the
 * same port.
 */
interface ConditionEvaluator
{
    /**
     * The names of the fields the condition reads, each once.
     *
     * @param array<array-key, mixed> $condition
     *
     * @return list<string>
     */
    public function fields(array $condition): array;

    /**
     * Refuses a condition the evaluator does not read, or that reads a field it may not: one missing from `$fields`, or
     * with values its type never takes.
     *
     * @param array<array-key, mixed> $condition
     * @param array<string, Field>    $fields    the fields the condition may read, by name
     *
     * @throws InvalidForm
     */
    public function check(array $condition, array $fields): void;

    /**
     * Whether the condition holds for these values; a field not asked has no value.
     *
     * @param array<array-key, mixed>         $condition a condition `check()` accepted
     * @param array<string, string|bool|null> $values
     */
    public function holds(array $condition, array $values): bool;
}
