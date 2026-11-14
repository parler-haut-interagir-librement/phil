<?php

declare(strict_types=1);

namespace Phil\FormLogic\Logic;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\Typing\Schema;
use Phil\FormLogic\Typing\TypeChecker;
use Phil\FormLogic\Typing\VariableDefinition;
use Phil\FormLogic\Typing\VariableNames;
use Phil\FormLogic\Typing\VariableType;

/**
 * The logic of a form: its field conditions and computed variables, in the order they are evaluated. Each step reads
 * the answer, the fields hidden before it read as unanswered, and the variables computed before it; it never reads a
 * step that comes after it, so the order says everything and no step waits for another.
 *
 * The same logic runs on the server, which judges, and in the browser, which shows and hides: `toArray()` gives the
 * Stimulus controller its rules.
 *
 * @phpstan-type Step array{field: string, when: mixed}|array{compute: string, type: string, value: mixed}
 */
final readonly class FormLogic
{
    /** The most steps a form's logic has. */
    public const int MAX_STEPS = 500;

    /**
     * @param list<FieldCondition|ComputedVariable> $steps
     *
     * @throws InvalidLogic when two steps share a name, a step reads one after it, or there are too many
     */
    public function __construct(public array $steps = [])
    {
        if (\count($steps) > self::MAX_STEPS) {
            throw InvalidLogic::because(\sprintf("A form's logic has at most %d steps.", self::MAX_STEPS));
        }

        $names = new VariableNames();
        $later = [];

        foreach ($steps as $step) {
            $name = $this->nameOf($step);

            if (isset($later[$name])) {
                throw InvalidLogic::because(\sprintf('"%s" has one condition or computation only.', $name));
            }

            $later[$name] = true;
        }

        foreach ($steps as $step) {
            $name = $this->nameOf($step);

            foreach ($names->of($this->expressionOf($step)) as $read) {
                if (isset($later[$read])) {
                    throw InvalidLogic::because(\sprintf('"%s" reads "%s", which comes after it or is itself.', $name, $read));
                }
            }

            unset($later[$name]);
        }
    }

    /**
     * Reads the logic back from `toArray()`.
     *
     * @param list<mixed> $steps
     *
     * @throws InvalidLogic when a step is not of either shape, or its rule not of the subset
     */
    public static function fromArray(array $steps, JsonLogicReader $reader = new JsonLogicReader()): self
    {
        $read = [];

        foreach ($steps as $step) {
            if (\is_array($step) && \is_string($step['field'] ?? null) && \array_key_exists('when', $step) && 2 === \count($step)) {
                $read[] = new FieldCondition($step['field'], $reader->parse($step['when']));

                continue;
            }

            $type = \is_array($step) && \is_string($step['type'] ?? null) ? VariableType::tryFrom($step['type']) : null;

            if (null === $type || !\is_string($step['compute'] ?? null) || !\array_key_exists('value', $step) || 3 !== \count($step)) {
                throw InvalidLogic::because('A step is {"field": name, "when": rule} or {"compute": name, "type": type, "value": rule}.');
            }

            $read[] = new ComputedVariable($step['compute'], $reader->parse($step['value']), $type);
        }

        return new self($read);
    }

    /**
     * Checks every step against the types of the fields, each computed variable then readable by the steps after it
     * with its own type.
     *
     * @throws InvalidLogic naming the first rule a step breaks
     */
    public function check(Schema $fields, TypeChecker $checker = new TypeChecker()): void
    {
        $schema = $fields;

        foreach ($this->steps as $step) {
            $checker->check($this->expressionOf($step), $schema);

            if ($step instanceof ComputedVariable) {
                $schema = $schema->with(new VariableDefinition($step->name, $step->type));
            }
        }
    }

    /**
     * The steps as data, each rule as JsonLogic: for storage, and for the Stimulus controller.
     *
     * @return list<Step>
     */
    public function toArray(): array
    {
        return array_map(static fn (FieldCondition|ComputedVariable $step): array => $step instanceof FieldCondition
            ? ['field' => $step->field, 'when' => $step->shownWhen->jsonLogic()]
            : ['compute' => $step->name, 'type' => $step->type->value, 'value' => $step->expression->jsonLogic()], $this->steps);
    }

    private function nameOf(FieldCondition|ComputedVariable $step): string
    {
        return $step instanceof FieldCondition ? $step->field : $step->name;
    }

    private function expressionOf(FieldCondition|ComputedVariable $step): Expression
    {
        return $step instanceof FieldCondition ? $step->shownWhen : $step->expression;
    }
}
