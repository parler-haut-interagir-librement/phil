<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Money;

/**
 * Turns the discounts granted by hand into `Promotion` adjustments of origin `manual:<key>`, after the options and
 * the catalogue's promotions and before the taxes, and leaves the other promotions alone.
 *
 * A discount on a line takes from what is left of the line once its options and earlier promotions apply; a discount
 * on the whole order is shared between its lines in proportion to what is left of each, so that each line is taxed
 * once discounted. A discount never brings a line below zero.
 */
final readonly class ManualDiscountProcessor implements OrderProcessor
{
    public const string ORIGIN_PREFIX = 'manual:';

    #[Override]
    public function process(Order $order): void
    {
        $promotions = [];

        foreach ($order->adjustments(AdjustmentType::Promotion) as $promotion) {
            if (null === $promotion->origin || !str_starts_with($promotion->origin, self::ORIGIN_PREFIX)) {
                $promotions[] = $promotion;
            }
        }

        $left = $this->left($order, $promotions);

        foreach ($order->manualDiscounts() as $discount) {
            foreach ($this->shares($discount, $order, $left) as $reference => $amount) {
                if ($amount->isPositive()) {
                    $line = SellableReference::fromValue($reference);
                    $promotions[] = Adjustment::of(AdjustmentType::Promotion, $discount->reason, $amount->negate(), $line, origin: self::ORIGIN_PREFIX.$discount->key);
                    $left[$reference] = $left[$reference]->subtract($amount);
                }
            }
        }

        $order->replaceAdjustments(AdjustmentType::Promotion, $promotions);
    }

    /**
     * What is left of each line once its options and the promotions kept apply, never below zero.
     *
     * @param list<Adjustment> $promotions
     *
     * @return array<string, Money> keyed by the line's reference value
     */
    private function left(Order $order, array $promotions): array
    {
        $left = [];

        foreach ($order->lines() as $line) {
            $left[$line->reference->value()] = $line->subtotal();
        }

        foreach ([...$order->adjustments(AdjustmentType::Option), ...$promotions] as $adjustment) {
            if (null !== $adjustment->line && isset($left[$adjustment->line->value()])) {
                $left[$adjustment->line->value()] = $left[$adjustment->line->value()]->add($adjustment->amount);
            }
        }

        return array_map(static fn (Money $amount): Money => $amount->isNegative() ? Money::zero($amount->currency) : $amount, $left);
    }

    /**
     * What one discount takes from each line.
     *
     * @param array<string, Money> $left
     *
     * @return array<string, Money>
     */
    private function shares(ManualDiscount $discount, Order $order, array $left): array
    {
        if ($discount->line instanceof SellableReference) {
            $value = $discount->line->value();

            return isset($left[$value]) ? [$value => $discount->of($left[$value])] : [];
        }

        $base = Money::zero($order->currency);
        $ratios = [];

        foreach ($left as $amount) {
            $base = $base->add($amount);
            $ratios[] = $amount->minorUnits;
        }

        $total = $discount->of($base);

        if (!$total->isPositive()) {
            return [];
        }

        return array_combine(array_keys($left), $total->allocate(...$ratios));
    }
}
