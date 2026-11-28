<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Condition;

use Phil\ContentAccess\AccessFacts;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `access.member`: the viewer is signed in. Takes no operand.
 */
final readonly class MemberCondition implements ConditionType
{
    public function name(): string
    {
        return 'access.member';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema();
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $id = $facts->has(AccessFacts::VIEWER_ID) ? $facts->get(AccessFacts::VIEWER_ID) : null;

        return \is_string($id) ? Verdict::pass('access.member') : Verdict::fail('access.anonymous');
    }
}
