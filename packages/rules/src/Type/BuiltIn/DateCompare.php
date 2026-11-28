<?php

declare(strict_types=1);

namespace Phil\Rules\Type\BuiltIn;

use DateTimeInterface;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `date.compare`: the calendar date of the fact, in the fact's own time zone, falls `before`, `after`, `on-or-before`
 * or `on-or-after` the date. Dates are compared as `YYYY-MM-DD` texts, never as objects.
 */
final readonly class DateCompare implements ConditionType
{
    public function name(): string
    {
        return 'date.compare';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(
            Operand::identifier('fact'),
            Operand::choice('operator', ['before', 'after', 'on-or-before', 'on-or-after']),
            Operand::date('date'),
        );
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $name = $operands->string('fact');
        $fact = Read::fact($facts, $name);

        if ($fact instanceof Verdict) {
            return $fact;
        }

        if (!$fact instanceof DateTimeInterface) {
            return Read::wrongType($name, 'date');
        }

        $order = strcmp($fact->format('Y-m-d'), $operands->string('date'));
        $operator = $operands->string('operator');
        $holds = match ($operator) {
            'before' => $order < 0,
            'after' => $order > 0,
            'on-or-before' => $order <= 0,
            default => $order >= 0,
        };

        return $holds
            ? Verdict::pass('date.compared', ['fact' => $name, 'operator' => $operator])
            : Verdict::fail('date.out-of-range', ['fact' => $name, 'operator' => $operator]);
    }
}
