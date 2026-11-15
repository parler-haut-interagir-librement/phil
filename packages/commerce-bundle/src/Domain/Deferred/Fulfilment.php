<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Deferred;

/**
 * When the goods of a deferred sale may leave the shop.
 */
enum Fulfilment: string
{
    /** Once the deposit is paid: the buyer has the goods and pays the balance later, as with installments. */
    case OnDeposit = 'on_deposit';

    /** Once the balance is paid: the goods wait for the buyer, as with a preorder or a layaway. */
    case OnBalance = 'on_balance';
}
