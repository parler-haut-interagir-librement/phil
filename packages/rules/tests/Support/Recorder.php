<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Support;

use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * A condition type for the tests: `test.flag` holds when its `holds` operand is true, and records each evaluation by
 * its `label`.
 */
final class Recorder implements ConditionType
{
    /** @var list<string> */
    public array $evaluated = [];

    public function name(): string
    {
        return 'test.flag';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::boolean('holds'), Operand::identifier('label', required: false));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $label = $operands->has('label') ? $operands->string('label') : '?';
        $this->evaluated[] = $label;

        return $operands->bool('holds') ? Verdict::pass('flag.up', ['label' => $label]) : Verdict::fail('flag.down', ['label' => $label]);
    }
}
