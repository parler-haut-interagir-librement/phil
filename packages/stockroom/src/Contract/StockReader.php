<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\Holding;
use Phil\Stockroom\Item;

/**
 * What another package reads of the stock, without moving it: an item, where its pieces are, and how many may leave —
 * those in the stockroom's containers, none when the item may not leave as it is. A rental reads it to offer what is in
 * stock.
 */
interface StockReader
{
    public function item(string $id): ?Item;

    /**
     * The pieces of an item that may leave now.
     */
    public function available(string $item): int;

    /**
     * Where the pieces of an item are: in containers, at events, with holders.
     *
     * @return list<Holding>
     */
    public function holdings(string $item): array;
}
