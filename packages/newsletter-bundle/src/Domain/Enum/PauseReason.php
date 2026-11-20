<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * Why a send run was paused.
 */
enum PauseReason: string
{
    /** The share of attempts the mail transport refused crossed its threshold. */
    case TransportFailures = 'transport_failures';

    /** The share of messages that bounced crossed its threshold. */
    case Bounces = 'bounces';

    /** The share of messages reported as spam crossed its threshold. */
    case Complaints = 'complaints';

    /** An operator paused it. */
    case Operator = 'operator';
}
