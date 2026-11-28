<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Condition;

use Phil\AudienceSegments\Contract\Supporters;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `supporter.active`: the member supports now, at one of the `tiers` when given.
 */
final readonly class SupporterActive implements ConditionType
{
    public function __construct(private Supporters $supporters)
    {
    }

    public function name(): string
    {
        return 'supporter.active';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifierList('tiers', required: false, maxItems: 50));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $tiers = $operands->has('tiers') ? $operands->strings('tiers') : [];

        return Member::verdict($this->supporters->isActive($member, $tiers), 'supporter.active', 'supporter.inactive', ['tiers' => \count($tiers)]);
    }
}
