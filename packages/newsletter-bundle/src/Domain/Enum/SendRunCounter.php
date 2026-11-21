<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * What a send run counts as its messages go out, each count added to in the store, never written over.
 */
enum SendRunCounter: string
{
    /** A message sent. */
    case Sent = 'sent';

    /** An attempt the mail transport refused; a message sent after a retry counts in both. */
    case Failed = 'failed';

    /** A message not sent: the subscriber left, paused or is suppressed, or the run was cancelled. */
    case Skipped = 'skipped';

    /** A message sent that bounced. */
    case Bounced = 'bounced';

    /** A message sent that its recipient reported as spam. */
    case Complained = 'complained';
}
