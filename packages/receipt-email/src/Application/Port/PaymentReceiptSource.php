<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;

/**
 * Reads a payment receipt or a credit note by its number, as the system that issued it keeps it.
 * `Infrastructure\Commerce\CommercePaymentReceipts` reads those of `phil/commerce-bundle`.
 */
interface PaymentReceiptSource
{
    public function find(string $number): ?PaymentReceipt;
}
