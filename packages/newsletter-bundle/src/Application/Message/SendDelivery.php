<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to send one queued message. It carries identifiers only: what it is about is read from the store when it is handled.
 */
final readonly class SendDelivery
{
    public function __construct(
        public string $deliveryId,
    ) {
    }
}
