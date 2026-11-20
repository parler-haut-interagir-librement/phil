<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\ImmediateChange;
use Phil\Money\Money;

/**
 * Prorates each line `subscription:<id>` of an order, as `Proration` adjustments on the line, which the core taxes
 * with it:
 *
 * - **the time already used**, for a change at once within the same period: the new tier is charged for the share of
 *   the period left, so the rest of its price is taken off, origin `proration:time`;
 * - **the credit of the change**, the unused part of the period changed from, origin `proration:credit`;
 * - **the credit carried over** from an earlier change, origin `proration:carried`.
 *
 * The credits never bring the line below zero: what a line does not use is carried over to the next order when that
 * order is paid. The processor runs after the options and the promotions, before the taxes, and changes nothing but
 * its own adjustments.
 */
final readonly class ProrationOrderProcessor implements OrderProcessor
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
    ) {
    }

    #[Override]
    public function process(Order $order): void
    {
        $adjustments = [];

        foreach ($order->lines() as $line) {
            if (OfferSellables::SUBSCRIPTION_TYPE !== $line->reference->type) {
                continue;
            }

            $subscription = $this->subscriptions->find($line->reference->id);

            if (!$subscription instanceof Subscription || !$subscription->currency()->equals($order->currency)) {
                continue;
            }

            $base = OrderedLine::base($order, $line->reference);

            if (!$base->isPositive()) {
                continue;
            }

            $change = $subscription->pendingChange();
            $charge = $base;
            $changeCredit = Money::zero($order->currency);

            if ($change instanceof ImmediateChange) {
                $charge = $change->charge($base);
                $changeCredit = $change->credit;
            }

            $amounts = [
                OrderedLine::UNUSED_TIME => ['Time already used', $base->subtract($charge)],
                OrderedLine::CHANGE_CREDIT => ['Credit for the unused period', $this->least($changeCredit, $charge)],
            ];
            $amounts[OrderedLine::CARRIED_CREDIT] = ['Credit carried over', $this->least($subscription->carriedCredit(), $charge->subtract($amounts[OrderedLine::CHANGE_CREDIT][1]))];

            foreach ($amounts as $origin => [$label, $amount]) {
                if ($amount->isPositive()) {
                    $adjustments[] = Adjustment::of(AdjustmentType::Proration, $label, $amount->negate(), $line->reference, origin: $origin);
                }
            }
        }

        $order->replaceAdjustments(AdjustmentType::Proration, $adjustments);
    }

    private function least(Money $left, Money $right): Money
    {
        return $left->isGreaterThan($right) ? $right : $left;
    }
}
