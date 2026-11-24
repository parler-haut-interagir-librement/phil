<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application;

/**
 * One message of the bounce mailbox: what the adapter names it by, and its raw text as the mail server stored it.
 */
final readonly class MailboxMessage
{
    public function __construct(
        public string $id,
        public string $raw,
    ) {
    }
}
