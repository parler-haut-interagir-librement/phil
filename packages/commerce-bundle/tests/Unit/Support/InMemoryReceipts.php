<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;

/**
 * Receipts and credit notes in memory, in the order they were added.
 */
final class InMemoryReceipts implements ReceiptRepository
{
    /** @var list<Receipt> */
    public array $receipts = [];

    #[Override]
    public function find(string $number): ?Receipt
    {
        return array_find($this->receipts, static fn (Receipt $receipt): bool => $receipt->number === $number);
    }

    #[Override]
    public function receiptOf(string $orderNumber): ?Receipt
    {
        return array_find($this->receipts, static fn (Receipt $receipt): bool => DocumentKind::Receipt === $receipt->kind && $receipt->orderNumber === $orderNumber);
    }

    #[Override]
    public function creditNotesOf(string $receiptNumber): array
    {
        return array_values(array_filter($this->receipts, static fn (Receipt $receipt): bool => $receipt->creditedReceipt === $receiptNumber));
    }

    #[Override]
    public function add(Receipt $receipt): void
    {
        $this->receipts[] = $receipt;
    }
}
