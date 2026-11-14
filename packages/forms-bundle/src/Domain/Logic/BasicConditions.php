<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Logic;

use Override;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;

/**
 * The conditions the bundle reads by itself, three operators of JsonLogic on one field each:
 *
 * - `{"==": [{"var": "team"}, "Blue"]}`, the field has this value — `true` or `false` for a checkbox;
 * - `{"!=": [{"var": "team"}, "Blue"]}`, the field has another value, or none — left empty, as JsonLogic reads it;
 * - `{"in": [{"var": "team"}, ["Blue", "Green"]]}`, the field has one of these values — not for a checkbox.
 *
 * Values are compared strictly, their type checked when the form is defined: a choice field is compared with its
 * choices only. A field left empty satisfies `!=` only; a field not asked satisfies none of them, `Form::holds()`
 * seeing to it before the evaluator is asked.
 */
final readonly class BasicConditions implements ConditionEvaluator
{
    /** The most values an `in` lists. */
    public const int MAX_VALUES = 100;

    #[Override]
    public function fields(array $condition): array
    {
        return [$this->parse($condition)[1]];
    }

    #[Override]
    public function check(array $condition, array $fields): void
    {
        [$operator, $name, $operand] = $this->parse($condition);
        $field = $fields[$name] ?? throw InvalidForm::because(\sprintf('A condition reads "%s", which is not a field asked before it.', $name));
        $values = \is_array($operand) ? $operand : [$operand];

        if (FieldType::Checkbox === $field->type) {
            if ('in' === $operator || !\is_bool($operand)) {
                throw InvalidForm::because(\sprintf('A condition on the checkbox "%s" compares it with true or false.', $name));
            }

            return;
        }

        foreach ($values as $value) {
            if (!\is_string($value) || (FieldType::Choice === $field->type && !\in_array($value, $field->choices, true))) {
                throw InvalidForm::because(\sprintf('A condition compares "%s" with a value it never takes.', $name));
            }
        }
    }

    #[Override]
    public function holds(array $condition, array $values): bool
    {
        [$operator, $name, $operand] = $this->parse($condition);
        $value = $values[$name] ?? null;

        return match ($operator) {
            '==' => $value === $operand,
            '!=' => $value !== $operand,
            default => \is_array($operand) && \in_array($value, $operand, true),
        };
    }

    /**
     * @param array<array-key, mixed> $condition
     *
     * @return array{0: '=='|'!='|'in', 1: string, 2: list<mixed>|string|bool}
     *
     * @throws InvalidForm when the condition is not one of the three shapes
     */
    private function parse(array $condition): array
    {
        $operator = array_key_first($condition);
        $arguments = null === $operator ? null : $condition[$operator];

        if (1 !== \count($condition) || !\in_array($operator, ['==', '!=', 'in'], true) || !\is_array($arguments) || !array_is_list($arguments) || 2 !== \count($arguments)) {
            throw InvalidForm::because('A condition is {"==": …}, {"!=": …} or {"in": …} with two arguments.');
        }

        [$variable, $operand] = $arguments;

        if (!\is_array($variable) || 1 !== \count($variable) || !\is_string($variable['var'] ?? null)) {
            throw InvalidForm::because('A condition reads one field, its first argument {"var": "name"}.');
        }

        if ('in' === $operator) {
            if (!\is_array($operand) || !array_is_list($operand) || [] === $operand || \count($operand) > self::MAX_VALUES) {
                throw InvalidForm::because(\sprintf('A condition "in" lists 1 to %d values.', self::MAX_VALUES));
            }

            return [$operator, $variable['var'], $operand];
        }

        if (!\is_string($operand) && !\is_bool($operand)) {
            throw InvalidForm::because('A condition compares a field with a text, true or false.');
        }

        return [$operator, $variable['var'], $operand];
    }
}
