<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * What a message received on the bounce mailbox says of the message sent.
 */
enum BounceKind: string
{
    /** The mailbox does not exist, or will never accept the message: a permanent failure against the address. */
    case Hard = 'hard';
    /** A failure that may pass: a delay, a full mailbox, a message refused by a filter, a server down. */
    case Soft = 'soft';
    /** An automatic reply of the recipient: out of the office, a ticket opened, an address changed. */
    case AutoReply = 'auto_reply';
    /** The recipient, or the mailbox provider for them, reported the message as unwanted. */
    case Complaint = 'complaint';
    /** An abuse report that is no complaint: a message reported as not spam, a failure of authentication. */
    case Feedback = 'feedback';
    /** A notification of success: delivered, relayed or expanded. */
    case Delivered = 'delivered';
    /** Nothing the parser or the rules recognise. */
    case Unknown = 'unknown';
}
