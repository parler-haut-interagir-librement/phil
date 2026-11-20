<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Money;

/**
 * The waiver of the delivery charge an order's free shipping gives: it runs after the processor that computes the
 * shipping adjustments, and adds one shipping adjustment that takes back what they charge, labelled and with the
 * origin of the promotion that gave it — the first one, when several do.
 *
 * It reads the neutral adjustment `PromotionEvaluator` left on the order for the free shipping, and keeps every
 * shipping adjustment of the delivery itself, so that the receipt shows the charge and its waiver. It replaces only its
 * own waivers: running it twice changes nothing.
 */
final readonly class FreeShippingProcessor implements OrderProcessor
{
    /** The processor of the logistics module that computes the shipping adjustments. */
    public const string SHIPPING_PROCESSOR = 'Phil\\CommerceLogisticsBundle\\Application\\ShippingProcessor';

    #[Override]
    public function process(Order $order): void
    {
        $delivery = [];

        foreach ($order->adjustments(AdjustmentType::Shipping) as $adjustment) {
            if (null === PromotionEvaluator::originOf($adjustment)) {
                $delivery[] = $adjustment;
            }
        }

        $waiver = array_find($order->adjustments(AdjustmentType::Promotion), static fn (Adjustment $adjustment): bool => !$adjustment->line instanceof SellableReference && $adjustment->neutral && $adjustment->amount->isZero() && null !== PromotionEvaluator::originOf($adjustment));
        $charged = Money::sum(Money::zero($order->currency), ...array_map(static fn (Adjustment $adjustment): Money => $adjustment->amount, array_filter($delivery, static fn (Adjustment $adjustment): bool => !$adjustment->neutral)));

        if ($waiver instanceof Adjustment && $charged->isPositive()) {
            $delivery[] = Adjustment::of(AdjustmentType::Shipping, $waiver->label, $charged->negate(), origin: $waiver->origin);
        }

        $order->replaceAdjustments(AdjustmentType::Shipping, $delivery);
    }
}
