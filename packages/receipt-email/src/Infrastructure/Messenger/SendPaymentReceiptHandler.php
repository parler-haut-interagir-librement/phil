<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Messenger;

use Phil\ReceiptEmail\Application\ReceiptEmails;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds the email of a payment receipt and sends it through a Mailer transport, in the worker: the email, which holds
 * the recipient's address and the receipt, never goes back onto a Messenger transport, as `MailerInterface` would send
 * it when `SendEmailMessage` is routed asynchronously. Register it with the tag `messenger.message_handler`.
 */
final readonly class SendPaymentReceiptHandler
{
    public function __construct(
        private ReceiptEmails $emails,
        private TransportInterface $transport,
    ) {
    }

    public function __invoke(SendPaymentReceipt $message): void
    {
        $this->transport->send($this->emails->payment($message->receiptNumber, $message->attachPdf));
    }
}
