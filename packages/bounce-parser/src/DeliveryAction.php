<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * The `Action` of a recipient in a delivery status notification (RFC 3464, section 2.3.3).
 */
enum DeliveryAction: string
{
    /** The message could not be delivered and will not be tried again. */
    case Failed = 'failed';
    /** The message is not delivered yet and will be tried again. */
    case Delayed = 'delayed';
    /** The message was delivered to the mailbox. */
    case Delivered = 'delivered';
    /** The message was passed to a system that sends no notification. */
    case Relayed = 'relayed';
    /** The message was delivered to a list or an alias, which sends it on. */
    case Expanded = 'expanded';
}
