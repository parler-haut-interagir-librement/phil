<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Repository;

use Phil\CommerceBundle\Domain\Model\Receipt;

/**
 * Finds and stores receipts and credit notes. `Infrastructure\Doctrine\DoctrineReceiptRepository` is the Doctrine
 * adapter.
 */
interface ReceiptRepository
{
    public function find(string $number): ?Receipt;

    /**
     * The receipt of an order, if one was issued; credit notes are not returned.
     */
    public function receiptOf(string $orderNumber): ?Receipt;

    /**
     * @return list<Receipt> the credit notes of a receipt, oldest first
     */
    public function creditNotesOf(string $receiptNumber): array;

    public function add(Receipt $receipt): void;
}
