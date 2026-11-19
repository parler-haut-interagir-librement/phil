<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Repository;

use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

/**
 * Where the requests are kept.
 */
interface TicketRepository
{
    public function find(ConversationId $conversation): ?Ticket;

    /**
     * Inserts the request, or updates its queue, status, assignee and moments.
     */
    public function save(Ticket $ticket): void;

    /**
     * Up to `$limit` requests of the queues given, latest change first, those of a status or an assignee when given.
     *
     * @param list<QueueName> $queues
     * @param positive-int    $limit
     * @param int<0, max>     $offset
     *
     * @return list<Ticket>
     */
    public function inQueues(array $queues, ?Status $status, ?ParticipantId $assignee, int $limit, int $offset = 0): array;

    /**
     * Up to `$limit` requests of a requester, latest change first.
     *
     * @param positive-int $limit
     * @param int<0, max>  $offset
     *
     * @return list<Ticket>
     */
    public function ofRequester(ParticipantId $requester, int $limit, int $offset = 0): array;

    /**
     * Every request a participant is the requester or the assignee of.
     *
     * @return list<Ticket>
     */
    public function concerning(ParticipantId $participant): array;

    /**
     * Erases the participant from the requests: requester and assignee wiped where they are theirs.
     *
     * @return int how many requests were changed
     */
    public function forget(ParticipantId $participant): int;
}
