<?php

declare(strict_types=1);

namespace Phil\Rules\Type\BuiltIn;

use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `fact.contains`: the fact is a list that holds the value, such as the groups of a person.
 */
final readonly class FactContains implements ConditionType
{
    public function name(): string
    {
        return 'fact.contains';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('fact'), Operand::scalar('value'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $name = $operands->string('fact');
        $fact = Read::fact($facts, $name);

        if ($fact instanceof Verdict) {
            return $fact;
        }

        if (!\is_array($fact)) {
            return Read::wrongType($name, 'list');
        }

        $value = $operands->scalar('value');

        foreach ($fact as $item) {
            if (Read::same($item, $value)) {
                return Verdict::pass('fact.contained', ['fact' => $name]);
            }
        }

        return Verdict::fail('fact.not-contained', ['fact' => $name]);
    }
}
