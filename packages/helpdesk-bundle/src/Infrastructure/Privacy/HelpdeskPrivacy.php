<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The help desk's part of an access or erasure request, the data subject being the participant's identifier: the
 * export lists the requests the person opened or was assigned, the erasure wipes them as requester and as assignee —
 * the requests stay, for the statistics of the queues. What the requests say is in their conversations, which
 * phil/conversation-bundle exports and erases.
 */
final readonly class HelpdeskPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'helpdesk';

    public function __construct(
        private TicketRepository $tickets,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $participant = $this->participant($subject);
        $tickets = $participant instanceof ParticipantId ? $this->tickets->concerning($participant) : [];

        return [] === $tickets ? [] : ['requests' => array_map(fn (Ticket $ticket): array => $this->row($ticket, $participant), $tickets)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $participant = $this->participant($subject);

        if ($participant instanceof ParticipantId) {
            $this->tickets->forget($participant);
        }

        return ErasureReport::of(self::MODULE, [HelpdeskTables::TICKET => ErasureEffect::Redacted]);
    }

    private function participant(DataSubject $subject): ?ParticipantId
    {
        try {
            return ParticipantId::fromString($subject->value);
        } catch (InvalidParticipant) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Ticket $ticket, ?ParticipantId $participant): array
    {
        return [
            'conversation' => $ticket->conversation->value,
            'role' => $participant instanceof ParticipantId && $ticket->isRequestedBy($participant) ? 'requester' : 'assignee',
            'queue' => $ticket->queue()->value,
            'status' => $ticket->status()->value,
            'opened_at' => $this->moment($ticket->openedAt),
            'updated_at' => $this->moment($ticket->updatedAt()),
            'closed_at' => $this->moment($ticket->closedAt()),
        ];
    }

    private function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(DateTimeInterface::ATOM);
    }
}
