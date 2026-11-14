<?php

declare(strict_types=1);

namespace Phil\FormLogic\FormsBundle;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\Typing\Schema;
use Phil\FormLogic\Typing\TypeChecker;
use Phil\FormLogic\Typing\VariableDefinition;
use Phil\FormLogic\Typing\VariableNames;
use Phil\FormLogic\Typing\VariableType;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\ValueObject\Field;

/**
 * The conditions of `phil/forms-bundle` read as the whole JsonLogic subset of the library rather than its three
 * operators: `and`, `or`, `!`, intervals, `missing` and the rest, checked against the types of the fields when the form
 * is defined. The fields are typed by what they take: a choice by its choices, a checkbox as a boolean, a date or a
 * birth date as a day, any other as a text.
 *
 * An application uses it by aliasing the bundle's `ConditionEvaluator` to this class.
 */
final readonly class JsonLogicConditions implements ConditionEvaluator
{
    public function __construct(
        private JsonLogicReader $reader = new JsonLogicReader(),
        private Evaluator $evaluator = new Evaluator(),
        private TypeChecker $checker = new TypeChecker(),
    ) {
    }

    #[Override]
    public function fields(array $condition): array
    {
        return new VariableNames()->of($this->read($condition));
    }

    #[Override]
    public function check(array $condition, array $fields): void
    {
        $expression = $this->read($condition);
        $definitions = [];

        foreach ($fields as $field) {
            $definitions[] = $this->definition($field);
        }

        try {
            $this->checker->check($expression, new Schema($definitions));
        } catch (InvalidLogic $invalidLogic) {
            throw InvalidForm::because(\sprintf('A condition is refused: %s', $invalidLogic->getMessage()));
        }
    }

    #[Override]
    public function holds(array $condition, array $values): bool
    {
        return $this->evaluator->holds($this->read($condition), $values);
    }

    /**
     * @param array<array-key, mixed> $condition
     *
     * @throws InvalidForm when the condition is not one operation of the subset
     */
    private function read(array $condition): Expression
    {
        if (array_is_list($condition)) {
            throw InvalidForm::because('A condition is one operation, such as {"==": [{"var": "team"}, "Blue"]}.');
        }

        try {
            return $this->reader->parse($condition);
        } catch (InvalidLogic $invalidLogic) {
            throw InvalidForm::because(\sprintf('A condition is refused: %s', $invalidLogic->getMessage()));
        }
    }

    private function definition(Field $field): VariableDefinition
    {
        return match ($field->type) {
            FieldType::Choice => new VariableDefinition($field->name, VariableType::Choice, $field->choices),
            FieldType::Checkbox => new VariableDefinition($field->name, VariableType::Boolean),
            FieldType::Date, FieldType::BirthDate => new VariableDefinition($field->name, VariableType::Date),
            default => new VariableDefinition($field->name, VariableType::Text),
        };
    }
}
