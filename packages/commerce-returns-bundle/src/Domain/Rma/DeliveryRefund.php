<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Rma;

/**
 * When the delivery charge of an order is given back with one of its returns — once per order.
 */
enum DeliveryRefund: string
{
    /** Never: the delivery was made. */
    case Never = 'never';

    /** With a return whose every line is the shop's fault, or with the one that brings back the order's last units. */
    case ShopsFault = 'shops_fault';

    /** Only with the return that brings back the order's last units. */
    case WholeOrder = 'whole_order';

    /**
     * Whether a return gives the delivery back, from whether the shop is at fault for all its lines and whether it
     * brings back every unit of the order left.
     */
    public function refunds(bool $shopsFault, bool $wholeOrder): bool
    {
        return match ($this) {
            self::Never => false,
            self::ShopsFault => $shopsFault || $wholeOrder,
            self::WholeOrder => $wholeOrder,
        };
    }
}
