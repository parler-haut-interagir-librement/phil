<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;

/**
 * Lets an order leave once its payments cover what it is due: what the processors captured, less what went back, is
 * the order's total or more. A deposit alone, a payment still pending, a refund that leaves less than the total: the
 * order waits. An order whose total is zero is covered.
 */
final readonly class PaidShipmentAuthorization implements ShipmentAuthorization
{
    public function __construct(
        private Payments $payments,
    ) {
    }

    #[Override]
    public function authorizes(Order $order): bool
    {
        $payments = $this->payments->summary($order);

        return !$payments->net()->isLessThan($payments->total);
    }
}
