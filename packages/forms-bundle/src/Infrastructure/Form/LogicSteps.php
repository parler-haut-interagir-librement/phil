<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Form;

use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\ValueObject\Field;

/**
 * The conditions of the fields a page shows, as the steps of the Stimulus controller of `phil/form-logic`, which shows
 * and hides them as the form is filled: `{"field": name, "when": rule}`, in the form's order.
 *
 * The browser judges as `Form::accept()` does. A field is shown when its condition holds and every field it reads is
 * shown: a field that another condition reads gets a computed variable before its own step,
 * `{"compute": "_shown_0", "type": "boolean", "value": rule}`, and the conditions that read it require that variable
 * too — so that a field left empty and a field not asked, both without a value, do not read alike. A condition that
 * reads a field the page does not show — not for the entry's tiers — is `false`.
 *
 * @phpstan-type Step array{field: string, when: mixed}|array{compute: string, type: 'boolean', value: mixed}
 */
final readonly class LogicSteps
{
    /** What the name of each computed variable starts with: a field's name never starts with "_". */
    public const string PREFIX = '_shown_';

    public function __construct(private ConditionEvaluator $logic = new BasicConditions())
    {
    }

    /**
     * @param list<Field> $fields the fields the page shows, in the form's order
     *
     * @return list<Step>
     */
    public function of(array $fields): array
    {
        $shown = [];
        $reads = [];
        $read = [];

        foreach ($fields as $field) {
            $shown[] = $field->name;

            if (null !== $field->shownWhen) {
                $reads[$field->name] = $this->logic->fields($field->shownWhen);
                $read += array_fill_keys($reads[$field->name], true);
            }
        }

        $steps = [];
        $variables = [];

        foreach ($fields as $field) {
            if (null === $field->shownWhen) {
                continue;
            }

            $rule = $this->rule($field->shownWhen, $reads[$field->name], $shown, $variables);

            if (isset($read[$field->name])) {
                $variables[$field->name] = self::PREFIX.\count($variables);
                $steps[] = ['compute' => $variables[$field->name], 'type' => 'boolean', 'value' => $rule];
                $rule = ['var' => $variables[$field->name]];
            }

            $steps[] = ['field' => $field->name, 'when' => $rule];
        }

        return $steps;
    }

    /**
     * @param array<array-key, mixed> $condition
     * @param list<string>            $reads     the fields the condition reads
     * @param list<string>            $shown     the fields the page shows
     * @param array<string, string>   $variables the computed variable of each conditional field read before
     */
    private function rule(array $condition, array $reads, array $shown, array $variables): mixed
    {
        $required = [];

        foreach ($reads as $name) {
            if (!\in_array($name, $shown, true)) {
                return false;
            }

            if (isset($variables[$name])) {
                $required[] = ['var' => $variables[$name]];
            }
        }

        return [] === $required ? $condition : ['and' => [...$required, $condition]];
    }
}
