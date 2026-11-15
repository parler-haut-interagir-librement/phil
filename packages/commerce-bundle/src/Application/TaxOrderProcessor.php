<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\Tax\MultiRateTaxCalculator;

/**
 * Taxes each line on its subtotal with what its options add, after its promotions and its proration, with the rates the
 * `TaxRateResolver` gives for the line's tax category in the order's tax zone: one tax adjustment per line and per
 * rate, labelled with the rate's code.
 *
 * An order without a tax zone carries no tax adjustment. A line whose promotions bring it below zero is taxed on
 * a negative base, which is what `phil/tax` does with a credit.
 *
 * When the order's prices include the taxes, the taxes are taken out of each line instead, and their adjustments
 * are neutral: shown on the receipt, never added to the total.
 */
final readonly class TaxOrderProcessor implements OrderProcessor
{
    public function __construct(
        private TaxRateResolver $rates,
        private MultiRateTaxCalculator $calculator = new MultiRateTaxCalculator(),
    ) {
    }

    #[Override]
    public function process(Order $order): void
    {
        $zone = $order->taxZone();
        $adjustments = [];

        if (null !== $zone) {
            $promotions = $order->adjustmentsByLine(AdjustmentType::Promotion);
            $options = $order->adjustmentsByLine(AdjustmentType::Option);
            $prorations = $order->adjustmentsByLine(AdjustmentType::Proration);

            foreach ($order->lines() as $line) {
                $base = $line->subtotal();

                foreach ([...$options[$line->reference->value()] ?? [], ...$promotions[$line->reference->value()] ?? [], ...$prorations[$line->reference->value()] ?? []] as $adjustment) {
                    $base = $base->add($adjustment->amount);
                }

                $rates = $this->rates->ratesFor($line->taxCategory, $zone);
                $breakdown = $order->taxIncluded() ? $this->calculator->extract($base, $rates) : $this->calculator->calculate($base, $rates);

                foreach ($breakdown->lines as $taxLine) {
                    $adjustments[] = Adjustment::of(AdjustmentType::Tax, $taxLine->rate->code, $taxLine->amount, $line->reference, $order->taxIncluded());
                }
            }
        }

        $order->replaceAdjustments(AdjustmentType::Tax, $adjustments);
    }
}
