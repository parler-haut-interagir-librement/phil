<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Rma;

/**
 * Where a return stands.
 */
enum ReturnState: string
{
    /** Asked for by the customer, not yet looked at. */
    case Requested = 'requested';

    /**
     * Sent back to the customer for what the shop needs — a photo, a serial number —; requested again once given.
     */
    case AwaitingInformation = 'awaiting_information';

    /** Accepted: the goods may come back. */
    case Authorized = 'authorized';

    /** The goods came back, and were looked at. */
    case Received = 'received';

    /** Refunded, credited or exchanged: the return is over. */
    case Resolved = 'resolved';

    /** Refused. */
    case Rejected = 'rejected';

    /** Withdrawn before the goods came back. */
    case Cancelled = 'cancelled';

    /** Authorized, but the goods never came back in time. */
    case Expired = 'expired';

    /**
     * Whether the return still holds its quantities: a rejected, cancelled or expired one frees them for another
     * return.
     */
    public function holds(): bool
    {
        return match ($this) {
            self::Requested, self::AwaitingInformation, self::Authorized, self::Received, self::Resolved => true,
            self::Rejected, self::Cancelled, self::Expired => false,
        };
    }
}
