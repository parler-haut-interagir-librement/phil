<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain;

/**
 * Where a bounce notice came from.
 */
enum NoticeSource: string
{
    /** A raw delivery status notification or abuse report, piped by the mail server or given as a file. */
    case Message = 'message';
    /** A message read from the bounce mailbox. */
    case Mailbox = 'mailbox';
    /** An event of a mail provider's webhook, parsed by its Mailer bridge. */
    case Webhook = 'webhook';
}
