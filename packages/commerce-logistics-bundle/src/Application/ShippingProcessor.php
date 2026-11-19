<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;

/**
 * The core's order processor for delivery: one shipping adjustment for the method the order chose, labelled as the
 * method — zero when it is free —, or none when no method is chosen or the chosen one no longer serves the order.
 * It runs after the catalogue's promotions it reads, and before the core's `TaxOrderProcessor`.
 */
final readonly class ShippingProcessor implements OrderProcessor
{
    public function __construct(
        private DeliveryOptions $options,
    ) {
    }

    #[Override]
    public function process(Order $order): void
    {
        $method = $this->options->chosen($order);

        $order->replaceAdjustments(AdjustmentType::Shipping, $method instanceof DeliveryMethod
            ? [Adjustment::of(AdjustmentType::Shipping, $method->label, $this->options->costFor($method, $order))]
            : []);
    }
}
