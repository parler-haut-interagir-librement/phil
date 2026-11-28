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
 * `fact.equals`: the fact holds the value. Numbers compare by value, 2 and 2.0 alike; anything else must be identical.
 */
final readonly class FactEquals implements ConditionType
{
    public function name(): string
    {
        return 'fact.equals';
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

        return Read::same($fact, $operands->scalar('value'))
            ? Verdict::pass('fact.equal', ['fact' => $name])
            : Verdict::fail('fact.different', ['fact' => $name]);
    }
}
