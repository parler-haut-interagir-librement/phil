<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Contract;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;

/**
 * The stock quantities the ledger's owners split: the units on hand at each location, which another module keeps.
 * `Infrastructure\Logistics\LogisticsStockMovements` moves the stock of `phil/commerce-logistics-bundle`.
 */
interface StockMovements
{
    /**
     * Adds a receipt's units to stock at its location, against its purchase order when it names one.
     */
    public function receive(Receipt $receipt, DateTimeImmutable $at): void;

    public function transfer(string $sku, string $from, string $to, int $quantity, DateTimeImmutable $at, ?string $reference): void;

    /**
     * Changes the units on hand by what a count found: fewer when negative.
     */
    public function adjust(string $sku, string $location, int $difference, DateTimeImmutable $at, ?string $reference): void;

    /**
     * Puts units sold and brought back on the shelf.
     */
    public function restock(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void;

    /**
     * Takes unsold units off the shelf for their consignor, who gets them back.
     */
    public function giveBack(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void;

    public function onHand(string $sku, string $location): int;
}
