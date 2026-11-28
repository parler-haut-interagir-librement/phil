<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Support;

use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;

/**
 * The receipts of `phil/commerce-bundle`, kept in memory.
 */
final class InMemoryReceipts implements ReceiptRepository
{
    /** @var array<string, Receipt> */
    private array $receipts = [];

    public function __construct(Receipt ...$receipts)
    {
        foreach ($receipts as $receipt) {
            $this->add($receipt);
        }
    }

    public function find(string $number): ?Receipt
    {
        return $this->receipts[$number] ?? null;
    }

    public function receiptOf(string $orderNumber): ?Receipt
    {
        foreach ($this->receipts as $receipt) {
            if (DocumentKind::Receipt === $receipt->kind && $receipt->orderNumber === $orderNumber) {
                return $receipt;
            }
        }

        return null;
    }

    public function creditNotesOf(string $receiptNumber): array
    {
        return array_values(array_filter($this->receipts, static fn (Receipt $receipt): bool => $receipt->creditedReceipt === $receiptNumber));
    }

    public function add(Receipt $receipt): void
    {
        $this->receipts[$receipt->number] = $receipt;
    }
}
