<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;

/**
 * Whether someone may force an order to a state outside its transitions: the application answers, usually through
 * its security voters. `DenyForcing`, the default, refuses everyone.
 */
interface TransitionForcing
{
    /**
     * @param string $actor the stable identifier of the person who forces the move
     */
    public function allows(Order $order, OrderState $to, string $actor): bool;
}
