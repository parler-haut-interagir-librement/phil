<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application\Contract;

use Phil\CommercePosBundle\Application\OfflineSale;
use Phil\CommercePosBundle\Domain\Model\CashSession;

/**
 * Whether whoever sends an offline sale may import it. The sale names its cashier (`by`) and its session, as the
 * register sent them: the application, which knows who is signed in, checks that this user may send sales rung up
 * by that cashier in that session — its own, or one of the register it works.
 *
 * `OfflineSales` asks it before anything is read or written; without it, the sale is taken as sent.
 */
interface OfflineSaleGate
{
    public function allows(OfflineSale $sale, CashSession $session): bool;
}
