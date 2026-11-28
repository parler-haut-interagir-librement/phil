<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Condition;

use Phil\ContentAccess\AccessFacts;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `access.role`: the viewer holds at least one of the roles, compared exactly.
 */
final readonly class RoleCondition implements ConditionType
{
    public function name(): string
    {
        return 'access.role';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifierList('roles'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $held = $facts->has(AccessFacts::VIEWER_ROLES) ? $facts->get(AccessFacts::VIEWER_ROLES) : [];

        if (\is_array($held)) {
            foreach ($operands->strings('roles') as $role) {
                if (\in_array($role, $held, true)) {
                    return Verdict::pass('access.role-held', ['role' => $role]);
                }
            }
        }

        return Verdict::fail('access.role-missing');
    }
}
