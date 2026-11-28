<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Messenger;

/**
 * Send the email of a template for a reference — an order, a ticket, a registration — once the message is handled.
 * The message carries identifiers only: the application's `DeliveryResolver` gives back the recipients and the values
 * then, so that no address and no value waits in a queue.
 */
final readonly class SendTemplatedEmail
{
    public function __construct(
        public string $template,
        public string $reference,
        public ?string $site = null,
    ) {
    }
}
