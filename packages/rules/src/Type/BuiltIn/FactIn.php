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
 * `fact.in`: the fact holds one of the values.
 */
final readonly class FactIn implements ConditionType
{
    public function name(): string
    {
        return 'fact.in';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('fact'), Operand::scalarList('values'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $name = $operands->string('fact');
        $fact = Read::fact($facts, $name);

        if ($fact instanceof Verdict) {
            return $fact;
        }

        foreach ($operands->scalars('values') as $value) {
            if (Read::same($fact, $value)) {
                return Verdict::pass('fact.listed', ['fact' => $name]);
            }
        }

        return Verdict::fail('fact.unlisted', ['fact' => $name]);
    }
}
