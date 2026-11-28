<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;

/**
 * Finds who a receipt is sent to, read when the email is built rather than carried by a message: the buyer of the
 * order, the donor. `null` when nobody is to receive it.
 */
interface RecipientDirectory
{
    public function paymentRecipient(PaymentReceipt $receipt): ?Recipient;

    public function donationRecipient(DonationReceipt $receipt): ?Recipient;
}
