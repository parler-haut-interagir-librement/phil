<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Phil\ReceiptEmail\Application\Port\DonationReceiptNumbering;
use Phil\ReceiptEmail\Domain\Repository\DonationReceiptRepository;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;

/**
 * Donation receipts kept in memory, numbered `D-000001`, `D-000002`….
 */
final class InMemoryDonationReceipts implements DonationReceiptRepository, DonationReceiptNumbering
{
    /** @var array<string, DonationReceipt> */
    public array $receipts = [];

    private int $last = 0;

    public function __construct(DonationReceipt ...$receipts)
    {
        foreach ($receipts as $receipt) {
            $this->add($receipt);
        }
    }

    public function next(): string
    {
        return \sprintf('D-%06d', ++$this->last);
    }

    public function find(string $serialNumber): ?DonationReceipt
    {
        return $this->receipts[$serialNumber] ?? null;
    }

    public function add(DonationReceipt $receipt): void
    {
        $this->receipts[$receipt->serialNumber] = $receipt;
    }
}
