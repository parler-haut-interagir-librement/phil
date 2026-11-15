<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * A subscriber's place on one list.
 */
enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
    /** Its confirmation was not sent: the domain of the address receives no mail. */
    case Refused = 'refused';
}
