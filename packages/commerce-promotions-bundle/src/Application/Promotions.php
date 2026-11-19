<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Exception\PromotionRefused;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Random\Randomizer;

/**
 * Batches of codes, and the uses of promotions, codes and customers counted once an order is placed.
 *
 * Uses are counted from what the placed order got — the origins of its promotion adjustments, frozen when it was
 * placed —, never by evaluating the promotions again, since they may have changed since. Each promotion is counted
 * once per order, however many times `redeem()` runs, and a use is claimed atomically against its limit.
 */
final readonly class Promotions
{
    /** Letters and digits a person reads back without confusing them: no 0, O, 1, I or L. */
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private PromotionRepository $promotions,
        private Randomizer $randomizer = new Randomizer(),
    ) {
    }

    /**
     * Creates a batch of codes for a promotion: `SPRING-7KQ4M2XA`…, each different, each stored.
     *
     * @return list<PromotionCode>
     */
    public function generateCodes(Promotion $promotion, int $count, string $prefix, ?int $usageLimit = 1): array
    {
        if ($count < 1 || $count > 10_000) {
            throw PromotionRefused::batch($count);
        }

        $codes = [];

        while (\count($codes) < $count) {
            $candidate = $prefix.$this->randomizer->getBytesFromString(self::ALPHABET, 8);

            if (isset($codes[$candidate]) || $this->promotions->findCode($candidate) instanceof PromotionCode) {
                continue;
            }

            $codes[$candidate] = PromotionCode::of($candidate, $promotion, $usageLimit);
            $this->promotions->addCode($codes[$candidate]);
        }

        return array_values($codes);
    }

    /**
     * Counts a use of each promotion the placed order got, of the code that unlocked it, and of the customer when the
     * promotion limits its uses per customer, once per order: the application calls it inside the transaction that
     * places the order. A promotion, a code or a customer that another order took to its limit meanwhile throws
     * `PromotionRefused`, and the placement is rolled back with the uses already claimed; the order, reopened and
     * recomputed, no longer gets it.
     *
     * @return list<PromotionRedemption> the promotions counted by this call; none when the order was counted before
     */
    public function redeem(Order $order): array
    {
        if (!$order->placedAt() instanceof DateTimeImmutable) {
            throw PromotionRefused::notPlaced($order->number);
        }

        $got = [];

        foreach ($order->adjustments(AdjustmentType::Promotion) as $adjustment) {
            $origin = PromotionEvaluator::originOf($adjustment);

            if (null !== $origin) {
                $got[$origin['promotion']] = [$origin['promotion'], $origin['code']];
            }
        }

        $customer = $order->customer();
        $redeemed = [];

        foreach ($got as [$promotion, $code]) {
            if ($this->promotions->findRedemption($order->number, $promotion) instanceof PromotionRedemption) {
                continue;
            }

            if (!$this->promotions->claimUse($promotion, $code)) {
                throw PromotionRefused::exhausted($promotion, $code);
            }

            $limit = $this->promotions->find($promotion)?->perCustomerLimit;

            if (null !== $limit && null !== $customer && !$this->promotions->claimCustomerUse($promotion, $customer, $limit)) {
                throw PromotionRefused::exhaustedForCustomer($promotion);
            }

            $redeemed[] = PromotionRedemption::of($order->number, $promotion, $code, $customer);
            $this->promotions->addRedemption(array_last($redeemed));
        }

        return $redeemed;
    }
}
