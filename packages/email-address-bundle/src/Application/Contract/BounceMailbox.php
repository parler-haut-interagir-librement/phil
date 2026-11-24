<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Contract;

use Phil\EmailAddressBundle\Application\MailboxMessage;

/**
 * The mailbox the bounces of the application's mail come back to: IMAP, a Maildir, a provider's API. The bundle reads
 * it through this port; an adapter fetches the messages not handled yet and marks each handled once its notices are
 * decided.
 */
interface BounceMailbox
{
    /**
     * @param positive-int $limit the most messages to fetch
     *
     * @return iterable<MailboxMessage> messages not acknowledged yet, the oldest first
     */
    public function fetch(int $limit): iterable;

    /**
     * Marks a message handled, so that it is not fetched again: moved, flagged or deleted, as the adapter says.
     */
    public function acknowledge(MailboxMessage $message): void;
}
