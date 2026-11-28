<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Phil\ReceiptEmail\Application\Port\RecipientDirectory;
use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;

/**
 * The same recipient for every receipt, or nobody.
 */
final readonly class FixedRecipients implements RecipientDirectory
{
    public function __construct(
        private ?Recipient $recipient,
    ) {
    }

    public function paymentRecipient(PaymentReceipt $receipt): ?Recipient
    {
        return $this->recipient;
    }

    public function donationRecipient(DonationReceipt $receipt): ?Recipient
    {
        return $this->recipient;
    }
}
