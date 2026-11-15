<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

/**
 * Holds and frees the stock of an order paid later: the logistics module, or the application, implements it.
 * Autoconfiguration tags each implementation `phil_commerce.stock_commitments`, and `DeferredSales` calls every one,
 * by the order's number: an implementation reads the order's lines from its own store.
 */
interface StockCommitments
{
    /**
     * Holds the goods of the order for its buyer. Called once per deferred sale, when its policy commits the stock.
     */
    public function commit(string $orderNumber): void;

    /**
     * Frees what `commit()` held: the deferred sale was cancelled before it was paid in full.
     */
    public function release(string $orderNumber): void;
}
