<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Messenger;

use Phil\ReceiptEmail\Application\ReceiptEmails;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds the email of an official donation receipt and sends it through a Mailer transport, in the worker: the email
 * never goes back onto a Messenger transport. Register it with the tag `messenger.message_handler`.
 */
final readonly class SendDonationReceiptHandler
{
    public function __construct(
        private ReceiptEmails $emails,
        private TransportInterface $transport,
    ) {
    }

    public function __invoke(SendDonationReceipt $message): void
    {
        $this->transport->send($this->emails->donation($message->serialNumber, $message->attachPdf));
    }
}
