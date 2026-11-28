<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Status;

/**
 * Where an entry stands: waiting its turn, holding an offer until its deadline, or out of the list — the offer
 * accepted, declined or lapsed, or the entry left.
 */
enum EntryStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Lapsed = 'lapsed';
    case Left = 'left';

    /**
     * Whether the entry is still in the list: waiting, or holding an offer.
     */
    public function isLive(): bool
    {
        return self::Waiting === $this || self::Offered === $this;
    }
}
