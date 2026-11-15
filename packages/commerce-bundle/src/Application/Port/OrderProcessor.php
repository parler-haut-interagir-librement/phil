<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\Model\Order;

/**
 * Recomputes one kind of adjustment of an order after each change: promotions, taxes, shipping, fees.
 *
 * A processor replaces every adjustment of its own type with `Order::replaceAdjustments()`, so that running it
 * twice changes nothing. Processors run in the order of their tags: promotions before taxes, so that the tax is
 * computed on the discounted line. A service implementing it is tagged `phil_commerce.order_processor` by
 * autoconfiguration; it names the processors it runs before or after with the tag's `before` and `after`, as
 * the modules do with `TaxOrderProcessor::class`.
 */
interface OrderProcessor
{
    public function process(Order $order): void;
}
