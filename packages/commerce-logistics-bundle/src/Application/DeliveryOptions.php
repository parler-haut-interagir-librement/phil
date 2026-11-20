<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Domain\Repository\DeliveryMethodRepository;
use Phil\Money\Money;

/**
 * The delivery methods an order may choose from, and what each costs it.
 */
final readonly class DeliveryOptions
{
    public function __construct(
        private DeliveryMethodRepository $methods,
    ) {
    }

    /**
     * @return list<DeliveryMethod> the methods serving the order's tax zone in its currency, by priority
     */
    public function for(Order $order): array
    {
        return array_values(array_filter($this->methods->activeMethods(), static fn (DeliveryMethod $method): bool => $method->serves($order->taxZone(), $order->currency)));
    }

    /**
     * The method the order chose, if it still serves it.
     */
    public function chosen(Order $order): ?DeliveryMethod
    {
        $code = $order->deliveryMethod();

        return array_find($this->for($order), static fn (DeliveryMethod $method): bool => $method->code === $code);
    }

    /**
     * What a method costs the order: free once its lines, after their promotions, reach the method's threshold.
     */
    public function costFor(DeliveryMethod $method, Order $order): Money
    {
        return $method->costFor($order->itemsTotal()->add($order->adjustmentsTotal(AdjustmentType::Promotion)));
    }
}
