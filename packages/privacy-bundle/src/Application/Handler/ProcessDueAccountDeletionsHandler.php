<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Handler;

use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends one message per request that is due, so that one slow erasure does not hold the others, a batch at a time: what
 * is left is found by the next tick. `ProcessDueAccountDeletions` carries nothing: the tag of the service names it.
 */
final readonly class ProcessDueAccountDeletionsHandler
{
    public const int BATCH = 100;

    public function __construct(
        private AccountDeletions $deletions,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->deletions->dueIdentifiers(self::BATCH) as $id) {
            $this->bus->dispatch(new AdvanceAccountDeletion($id));
        }

        $this->deletions->purgeExpiredExports();
    }
}
