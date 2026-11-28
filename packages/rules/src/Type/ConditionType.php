<?php

declare(strict_types=1);

namespace Phil\Rules\Type;

use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;

/**
 * A kind of leaf condition a package declares: a stable name, the schema of its operands and its evaluator. The name
 * is written in stored rules, so it never changes once published.
 *
 * An implementation keeps nothing from one evaluation to the next, so that one instance serves every request of a
 * worker, and reads its facts only through the source it is given.
 */
interface ConditionType
{
    /**
     * Lowercase words of letters and digits joined by `.`, `-` or `_`, such as `fact.equals` or `ticket.holder`.
     */
    public function name(): string;

    public function operands(): OperandSchema;

    /**
     * Whether the condition holds for these facts, and why.
     */
    public function evaluate(Operands $operands, FactSource $facts): Verdict;
}
