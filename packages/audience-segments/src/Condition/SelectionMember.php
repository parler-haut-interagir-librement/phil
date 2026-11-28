<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Condition;

use Phil\AudienceSegments\Contract\Participation;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `selection.member`: an organiser chose the member in the selection.
 */
final readonly class SelectionMember implements ConditionType
{
    public function __construct(private Participation $participation)
    {
    }

    public function name(): string
    {
        return 'selection.member';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('selection'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $selection = $operands->string('selection');

        return Member::verdict($this->participation->inSelection($member, $selection), 'selection.chosen', 'selection.not-chosen', ['selection' => $selection]);
    }
}
