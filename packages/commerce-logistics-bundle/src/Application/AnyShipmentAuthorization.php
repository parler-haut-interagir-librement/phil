<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;

/**
 * Lets an order leave when one of the authorizations does, in their order, the first yes ending the search; none, or
 * no authorization at all, keeps it.
 */
final readonly class AnyShipmentAuthorization implements ShipmentAuthorization
{
    /**
     * @param iterable<ShipmentAuthorization> $authorizations
     */
    public function __construct(
        private iterable $authorizations,
    ) {
    }

    #[Override]
    public function authorizes(Order $order): bool
    {
        foreach ($this->authorizations as $authorization) {
            if ($authorization->authorizes($order)) {
                return true;
            }
        }

        return false;
    }
}
