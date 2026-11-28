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
 * `fact.present`: the scope holds the fact, and it is not null.
 */
final readonly class FactPresent implements ConditionType
{
    public function name(): string
    {
        return 'fact.present';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('fact'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $name = $operands->string('fact');
        $fact = Read::fact($facts, $name);

        if ($fact instanceof Verdict) {
            return $fact;
        }

        return null === $fact
            ? Verdict::fail('fact.null', ['fact' => $name])
            : Verdict::pass('fact.present', ['fact' => $name]);
    }
}
