<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

/**
 * A request to the help desk: a conversation of phil/conversation-bundle, named by its identifier, with the queue it
 * waits in, its status, its requester — null once their data is erased — and the agent it is assigned to. The status
 * changes through the workflow `phil_helpdesk_ticket` only. Moments are kept in UTC.
 */
final class Ticket
{
    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $closedAt;

    public readonly DateTimeImmutable $openedAt;

    public function __construct(
        public readonly ConversationId $conversation,
        private QueueName $queue,
        private readonly ?ParticipantId $requester,
        DateTimeImmutable $openedAt,
        private Status $status = Status::New,
        private ?ParticipantId $assignee = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $closedAt = null,
    ) {
        $utc = new DateTimeZone('UTC');
        $this->openedAt = $openedAt->setTimezone($utc);
        $this->updatedAt = ($updatedAt ?? $openedAt)->setTimezone($utc);
        $this->closedAt = $closedAt?->setTimezone($utc);
    }

    public function queue(): QueueName
    {
        return $this->queue;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function requester(): ?ParticipantId
    {
        return $this->requester;
    }

    public function assignee(): ?ParticipantId
    {
        return $this->assignee;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function isRequestedBy(ParticipantId $participant): bool
    {
        return $this->requester instanceof ParticipantId && $this->requester->equals($participant);
    }

    /**
     * The marking of the workflow, read by its marking store.
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * Set by the workflow `phil_helpdesk_ticket` only, through its transitions.
     */
    public function setMarking(string $marking): void
    {
        $this->status = Status::from($marking);
    }

    public function assign(?ParticipantId $agent): void
    {
        $this->assignee = $agent;
    }

    public function move(QueueName $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * Notes that the request changed, now; a closed request keeps the moment it was closed.
     */
    public function touch(DateTimeImmutable $at): void
    {
        $at = $at->setTimezone(new DateTimeZone('UTC'));
        $this->updatedAt = $at;
        $this->closedAt = $this->status->isClosed() ? ($this->closedAt ?? $at) : null;
    }
}
