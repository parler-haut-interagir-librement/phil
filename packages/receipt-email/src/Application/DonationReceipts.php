<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

use DateTimeImmutable;
use Phil\ReceiptEmail\Application\Port\DonationReceiptNumbering;
use Phil\ReceiptEmail\Domain\Repository\DonationReceiptRepository;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;

/**
 * Issues official donation receipts, each numbered in the series of donation receipts and kept.
 *
 * The application calls it inside the transaction that keeps the receipt: the gift is checked before a number is
 * taken — `Donation::of()` refuses it first —, so a refused receipt never uses one.
 */
final readonly class DonationReceipts
{
    public function __construct(
        private DonationReceiptNumbering $numbering,
        private DonationReceiptRepository $receipts,
    ) {
    }

    public function issue(Donation $donation, DateTimeImmutable $at): DonationReceipt
    {
        $receipt = DonationReceipt::of($this->numbering->next(), $at, $donation);
        $this->receipts->add($receipt);

        return $receipt;
    }
}
