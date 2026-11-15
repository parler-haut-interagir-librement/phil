<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to send the confirmation of a double opt-in. It carries identifiers only: what it is about is read from the store when it is handled.
 */
final readonly class SendConfirmation
{
    public function __construct(
        public string $subscriberId,
        public string $listId,
    ) {
    }
}
