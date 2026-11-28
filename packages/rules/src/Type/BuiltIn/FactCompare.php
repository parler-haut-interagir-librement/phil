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
 * `fact.compare`: the fact is a number below (`lt`), at most (`lte`), above (`gt`) or at least (`gte`) the value.
 */
final readonly class FactCompare implements ConditionType
{
    public function name(): string
    {
        return 'fact.compare';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(
            Operand::identifier('fact'),
            Operand::choice('operator', ['lt', 'lte', 'gt', 'gte']),
            Operand::number('value'),
        );
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $name = $operands->string('fact');
        $fact = Read::fact($facts, $name);

        if ($fact instanceof Verdict) {
            return $fact;
        }

        if (!\is_int($fact) && !\is_float($fact)) {
            return Read::wrongType($name, 'number');
        }

        $value = $operands->float('value');
        $operator = $operands->string('operator');
        $holds = match ($operator) {
            'lt' => $fact < $value,
            'lte' => $fact <= $value,
            'gt' => $fact > $value,
            default => $fact >= $value,
        };

        return $holds
            ? Verdict::pass('fact.compared', ['fact' => $name, 'operator' => $operator])
            : Verdict::fail('fact.out-of-range', ['fact' => $name, 'operator' => $operator]);
    }
}
