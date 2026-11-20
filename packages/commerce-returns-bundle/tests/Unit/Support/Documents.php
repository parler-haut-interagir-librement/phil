<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

/**
 * The core's receipts and the numbers of its series, in memory; each number taken is counted.
 */
final class Documents implements ReceiptRepository, DocumentNumbering, DocumentSeries
{
    /** @var array<string, Receipt> */
    public array $receipts = [];

    /** @var array<string, int> */
    public array $sequences = [];

    /** @var list<callable(): void> run when a number is taken, as another transaction would meanwhile */
    public array $meanwhile = [];

    #[Override]
    public function find(string $number): ?Receipt
    {
        return $this->receipts[$number] ?? null;
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
        $this->receipts[$receipt->number] = $receipt;
    }

    #[Override]
    public function next(DocumentKind $kind): string
    {
        return strtoupper($kind->value).'-'.$this->take($kind->value);
    }

    #[Override]
    public function nextIn(DocumentType $type): string
    {
        return $type->format($this->take($type->code));
    }

    private function take(string $series): int
    {
        foreach ($this->meanwhile as $meanwhile) {
            $meanwhile();
        }

        $this->meanwhile = [];

        return $this->sequences[$series] = ($this->sequences[$series] ?? 0) + 1;
    }
}
