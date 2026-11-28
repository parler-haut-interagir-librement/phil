<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application\Port;

/**
 * Gives the next serial number of the donation receipts: a series of its own, sequential, without gaps, never shared
 * with the payment receipts. The application implements it on its storage, inside the transaction that keeps the
 * receipt, so that a number taken is never lost and two receipts never share one.
 */
interface DonationReceiptNumbering
{
    /**
     * @return string 1 to 32 visible ASCII characters
     */
    public function next(): string;
}
