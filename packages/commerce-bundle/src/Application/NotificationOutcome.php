<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

/**
 * What became of a verified notification. A webhook endpoint acknowledges every outcome but `UnknownPayment`, which
 * the processor should not send again; it asks for `UnknownPayment` to be sent again, since the payment may not be
 * stored yet.
 */
enum NotificationOutcome: string
{
    /** The payment moved. */
    case Applied = 'applied';

    /** The notification was applied before: a replay, which changes nothing. */
    case AlreadyApplied = 'already_applied';

    /** The notification does not apply to the payment as it stands; sending it again would not change that. */
    case Refused = 'refused';

    /** No payment of the method is known by the processor's reference, yet or at all. */
    case UnknownPayment = 'unknown_payment';

    /** The notification is of an event the store does not act on: it names no payment and changes nothing. */
    case Ignored = 'ignored';
}
