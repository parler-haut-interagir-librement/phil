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
 * `ticket.holder`: the member holds a ticket of the event, of one of the ticket types when `ticket_types` is given.
 */
final readonly class TicketHolder implements ConditionType
{
    public function __construct(private Participation $participation)
    {
    }

    public function name(): string
    {
        return 'ticket.holder';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('event'), Operand::identifierList('ticket_types', required: false, maxItems: 50));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $event = $operands->string('event');
        $types = $operands->has('ticket_types') ? $operands->strings('ticket_types') : [];

        return Member::verdict($this->participation->holdsTicket($member, $event, $types), 'ticket.held', 'ticket.not-held', ['event' => $event]);
    }
}
