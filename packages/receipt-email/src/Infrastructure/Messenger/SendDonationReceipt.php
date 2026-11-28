<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Messenger;

use Phil\ReceiptEmail\Domain\Guard;

/**
 * Asks a worker to email an official donation receipt. It carries the receipt's serial number and whether to attach
 * its PDF, nothing else: no address, no name, no amount. The worker reads the receipt and its recipient again.
 */
final readonly class SendDonationReceipt
{
    public function __construct(
        public string $serialNumber,
        public bool $attachPdf = false,
    ) {
        Guard::identifier($serialNumber, 'The serial number of a donation receipt', 32);
    }
}
