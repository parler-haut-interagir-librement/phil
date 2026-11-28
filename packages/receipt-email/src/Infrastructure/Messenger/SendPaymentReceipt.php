<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Messenger;

use Phil\ReceiptEmail\Domain\Guard;

/**
 * Asks a worker to email a payment receipt or a credit note. It carries the receipt's number and whether to attach
 * its PDF, nothing else: no address, no name, no amount. The worker reads the receipt and its recipient again.
 */
final readonly class SendPaymentReceipt
{
    public function __construct(
        public string $receiptNumber,
        public bool $attachPdf = false,
    ) {
        Guard::identifier($receiptNumber, 'The number of a receipt');
    }
}
