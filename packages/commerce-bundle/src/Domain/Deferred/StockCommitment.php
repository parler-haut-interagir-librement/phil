<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Deferred;

/**
 * When the stock of a deferred sale is committed to its buyer.
 */
enum StockCommitment: string
{
    /** When the sale opens, with its order: the goods are held for the buyer from then on. */
    case OnOrder = 'on_order';

    /** Once the balance is paid: until then, the goods may still sell to someone else. */
    case OnBalance = 'on_balance';
}
