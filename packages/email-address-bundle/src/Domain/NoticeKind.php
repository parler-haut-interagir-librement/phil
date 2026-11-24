<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain;

/**
 * What a bounce notice says of an address, whichever way it came: a message read by the bounce parser or an event of
 * a mail provider's webhook.
 */
enum NoticeKind: string
{
    /** A permanent failure: the mailbox does not exist or will never accept the message. */
    case Hard = 'hard';
    /** A failure that may pass: a full mailbox, a delay, a server down. */
    case Soft = 'soft';
    /** The recipient, or their mailbox provider, reported the message as unwanted. */
    case Complaint = 'complaint';
    /** The recipient asked their provider to stop the mail: a one-click unsubscription. */
    case Unsubscribe = 'unsubscribe';
    /** Something a person should read: a report that is not understood or not vouched for. */
    case Review = 'review';
}
