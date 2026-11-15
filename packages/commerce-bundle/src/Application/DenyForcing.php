<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Contract\TransitionForcing;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;

/**
 * Nobody forces an order: the default, until the application aliases its own `TransitionForcing`.
 */
final readonly class DenyForcing implements TransitionForcing
{
    #[Override]
    public function allows(Order $order, OrderState $to, string $actor): bool
    {
        return false;
    }
}
