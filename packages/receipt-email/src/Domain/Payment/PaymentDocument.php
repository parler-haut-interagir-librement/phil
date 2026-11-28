<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Payment;

/**
 * What a payment document is: each kind is numbered in its own series by the system that issues it.
 */
enum PaymentDocument: string
{
    /** What the buyer paid for: lines, charges and taxes as they were agreed to. */
    case Receipt = 'receipt';

    /** The reversal of a receipt, in whole or in part: its amounts are negative. */
    case CreditNote = 'credit_note';
}
