<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Subscription;

/**
 * Where a subscription stands.
 */
enum SubscriptionState: string
{
    /** Waiting for its first payment: no access yet. */
    case Pending = 'pending';

    /** In its free trial: access granted until the trial ends and the first period is paid. */
    case Trialing = 'trialing';

    /** Paid for the current period, or free. */
    case Active = 'active';

    /** Its renewal failed and is being retried: access kept while the retries last. */
    case PastDue = 'past_due';

    /** Over: cancelled and its period ended, its retries exhausted, or given up before its first payment. */
    case Ended = 'ended';
}
