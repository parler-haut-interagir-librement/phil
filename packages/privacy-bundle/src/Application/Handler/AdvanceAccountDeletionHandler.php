<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Handler;

use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;

/**
 * An erasure that fails throws from here, so that Messenger retries the message; the next sweep comes back to it too.
 */
final readonly class AdvanceAccountDeletionHandler
{
    public function __construct(
        private AccountDeletions $deletions,
    ) {
    }

    public function __invoke(AdvanceAccountDeletion $message): void
    {
        $this->deletions->advance($message->deletionId);
    }
}
