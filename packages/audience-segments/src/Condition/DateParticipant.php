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
 * `date.participant`: the member took part in the date of an event.
 */
final readonly class DateParticipant implements ConditionType
{
    public function __construct(private Participation $participation)
    {
    }

    public function name(): string
    {
        return 'date.participant';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('occurrence'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $occurrence = $operands->string('occurrence');

        return Member::verdict($this->participation->attended($member, $occurrence), 'date.attended', 'date.not-attended', ['occurrence' => $occurrence]);
    }
}
