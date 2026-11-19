<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Ticket;

/**
 * Where a request stands, the places of the workflow `phil_helpdesk_ticket`: new, until an agent takes it; open, while
 * the agents work on it; waiting for the requester's answer; resolved, which the requester may still reopen by
 * answering; closed, for good.
 */
enum Status: string
{
    case New = 'new';
    case Open = 'open';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function isClosed(): bool
    {
        return self::Closed === $this;
    }
}
