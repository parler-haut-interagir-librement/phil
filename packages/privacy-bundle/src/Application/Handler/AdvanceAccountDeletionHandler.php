<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Handler;

use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Exception\AccountErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;

/**
 * An erasure that fails throws from here, so that Messenger retries the message; the next sweep comes back to it too.
 *
 * What it throws names the request and the modules that failed, never the failures themselves: Messenger keeps the
 * message of the exception, and of every previous one, in the envelope it sends to the retry and failure transports.
 */
final readonly class AdvanceAccountDeletionHandler
{
    public function __construct(
        private AccountDeletions $deletions,
    ) {
    }

    /**
     * @throws AccountErasureIncomplete
     */
    public function __invoke(AdvanceAccountDeletion $message): void
    {
        try {
            $this->deletions->advance($message->deletionId);
        } catch (ErasureIncomplete $erasureIncomplete) {
            throw AccountErasureIncomplete::of($message->deletionId, $erasureIncomplete);
        }
    }
}
