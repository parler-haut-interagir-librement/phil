<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Repository;

use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;

/**
 * Keeps the donation receipts issued, each under its serial number, never changed afterwards: the application's
 * storage implements it, and keeps them for as long as the law asks.
 */
interface DonationReceiptRepository
{
    public function find(string $serialNumber): ?DonationReceipt;

    public function add(DonationReceipt $receipt): void;
}
