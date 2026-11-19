<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Delivery;

/**
 * How an order reaches its buyer.
 */
enum DeliveryKind: string
{
    /** By a carrier, to an address. */
    case Shipping = 'shipping';

    /** By the store's own hands, near it. */
    case LocalDelivery = 'local_delivery';

    /** Picked up by the buyer at a location. */
    case Pickup = 'pickup';
}
