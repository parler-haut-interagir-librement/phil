<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommercePromotionsBundle\Application\Contract\CustomerOwnership;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\Money\Money;

/**
 * Which promotions an order gets, and the discounts they give, line by line.
 *
 * Promotions are tried in the order `PromotionOrder` sets. One applies when its conditions hold for the order's
 * channel, customer group, currency and moment, its code was entered if it asks for one, the customer has uses left
 * and owns what it asks for, no promotion it is incompatible with applied before it, and its eligible lines reach its
 * minimum. **Discounts never take a line below zero**: each comes off what the promotions before it left. An
 * exclusive promotion that gives something stops the others.
 *
 * Each discount stays on its own line, a bundle's included, and keeps its origin — `promotion:<code>`, and
 * `promotion:<code>:<promotion code>` when a code unlocked it —, so that what a placed order got is read from the
 * order itself. A free shipping gives a neutral adjustment of zero on the order, which `FreeShippingProcessor` turns
 * into the waiver of the delivery charge. Codes are matched whatever their case, as a buyer types them from a flyer,
 * and each code entered is looked up once per evaluation.
 */
final readonly class PromotionEvaluator
{
    /** The start of the origin of every discount a promotion gives. */
    public const string ORIGIN = 'promotion:';

    public function __construct(
        private PromotionRepository $promotions,
        private ?PromotionTexts $texts = null,
        private ?CustomerOwnership $ownership = null,
    ) {
    }

    /**
     * @return list<array{promotion: Promotion, code: ?PromotionCode, adjustments: non-empty-list<Adjustment>}>
     */
    public function evaluate(Order $order, DateTimeImmutable $at): array
    {
        $entered = $this->enteredCodes($order);
        $lines = $order->lines();
        $channel = $order->channel();
        $customerGroup = $order->customerGroup();
        $customer = $order->customer();
        $currency = $order->currency;
        $left = [];

        foreach ($lines as $line) {
            $left[$line->reference->value()] = $line->subtotal();
        }

        $candidates = array_filter($this->promotions->active(), static fn (Promotion $promotion): bool => $promotion->appliesTo($channel, $customerGroup, $currency, $at));
        $labels = $this->texts?->of($candidates);
        $applied = [];

        foreach (PromotionOrder::sort($candidates) as $promotion) {
            $code = $this->codeFor($promotion, $entered);

            if (($promotion->requiresCode && !$code instanceof PromotionCode) || !$this->servesCustomer($promotion, $customer) || $this->clashes($promotion, $applied)) {
                continue;
            }

            $unlocking = $promotion->requiresCode ? $code : null;
            $origin = self::ORIGIN.$promotion->code.($unlocking instanceof PromotionCode ? ':'.$unlocking->code : '');
            $adjustments = $this->give($promotion, $order, $lines, $left, $labels?->label($promotion) ?? $promotion->label(), $origin);

            if ([] === $adjustments) {
                continue;
            }

            $applied[] = ['promotion' => $promotion, 'code' => $unlocking, 'adjustments' => $adjustments];

            if ($promotion->exclusive) {
                break;
            }
        }

        return $applied;
    }

    /**
     * What the promotion gives the order, taken off what is left of each line.
     *
     * @param list<OrderLine>      $lines
     * @param array<string, Money> $left  what each line is worth after the promotions before, lowered by what it gives
     *
     * @return list<Adjustment>
     */
    private function give(Promotion $promotion, Order $order, array $lines, array &$left, string $label, string $origin): array
    {
        $eligible = array_values(array_filter($lines, static fn (OrderLine $line): bool => ProductSellables::TYPE === $line->reference->type && $promotion->coversSku($line->reference->id)));

        if (PromotionAction::FreeShipping === $promotion->action) {
            return $promotion->waives($order->deliveryMethod()) && $this->reachesMinimum($promotion, $eligible, $left)
                ? [Adjustment::of(AdjustmentType::Promotion, $label, Money::zero($order->currency), neutral: true, origin: $origin)]
                : [];
        }

        $adjustments = [];

        foreach ($this->discounts($promotion, $eligible, $left) as [$line, $discount]) {
            $key = $line->reference->value();
            $discount = $discount->isGreaterThan($left[$key]) ? $left[$key] : $discount;

            if ($discount->isPositive()) {
                $left[$key] = $left[$key]->subtract($discount);
                $adjustments[] = Adjustment::of(AdjustmentType::Promotion, $label, $discount->negate(), $line->reference, origin: $origin);
            }
        }

        return $adjustments;
    }

    /**
     * The promotion and the code an adjustment's origin names, or `null` when another processor gave it.
     *
     * @return array{promotion: string, code: ?string}|null
     */
    public static function originOf(Adjustment $adjustment): ?array
    {
        if (null === $adjustment->origin || !str_starts_with($adjustment->origin, self::ORIGIN)) {
            return null;
        }

        $parts = explode(':', substr($adjustment->origin, \strlen(self::ORIGIN)), 2);

        return ['promotion' => $parts[0], 'code' => $parts[1] ?? null];
    }

    /**
     * Whether the customer may get it: uses left under its limit per customer, and one of the products it asks them
     * to own. A guest gets neither.
     */
    private function servesCustomer(Promotion $promotion, ?string $customer): bool
    {
        if (null === $promotion->perCustomerLimit && [] === $promotion->ownedSkus) {
            return true;
        }

        if (null === $customer) {
            return false;
        }

        if (null !== $promotion->perCustomerLimit && $this->promotions->customerUses($promotion->code, $customer) >= $promotion->perCustomerLimit) {
            return false;
        }

        return [] === $promotion->ownedSkus || (true === $this->ownership?->ownsAny($customer, $promotion->ownedSkus));
    }

    /**
     * @param list<array{promotion: Promotion, code: ?PromotionCode, adjustments: non-empty-list<Adjustment>}> $applied
     */
    private function clashes(Promotion $promotion, array $applied): bool
    {
        return null !== array_find($applied, static fn (array $before): bool => $promotion->isIncompatibleWith($before['promotion']));
    }

    /**
     * @param list<OrderLine>      $eligible
     * @param array<string, Money> $left
     */
    private function reachesMinimum(Promotion $promotion, array $eligible, array $left): bool
    {
        $subtotal = Money::sum(Money::zero($promotion->currency), ...array_map(static fn (OrderLine $line): Money => $left[$line->reference->value()], $eligible));

        return $subtotal->isPositive() && !$subtotal->isLessThan($promotion->minimumSubtotal());
    }

    /**
     * @param list<OrderLine>      $eligible
     * @param array<string, Money> $left     what each line is worth after the promotions before
     *
     * @return list<array{OrderLine, Money}> each eligible line and its discount, before it is capped by what is left
     */
    private function discounts(Promotion $promotion, array $eligible, array $left): array
    {
        if (PromotionAction::BundlePrice === $promotion->action) {
            $groups = $promotion->groups();
            $eligible = array_values(array_filter($eligible, static fn (OrderLine $line): bool => array_find($groups, static fn (BundleGroup $group): bool => $group->covers($line->reference->id)) instanceof BundleGroup));
        }

        if (!$this->reachesMinimum($promotion, $eligible, $left)) {
            return [];
        }

        $worth = array_map(static fn (OrderLine $line): Money => $left[$line->reference->value()], $eligible);
        $subtotal = Money::sum(Money::zero($promotion->currency), ...$worth);

        $amounts = match ($promotion->action) {
            PromotionAction::PercentOff => array_map(static fn (Money $each): Money => $each->allocate($promotion->partsPerMillion, 1_000_000 - $promotion->partsPerMillion)[0], $worth),
            PromotionAction::BuyNPayX => array_map(static fn (OrderLine $line): Money => $line->unitPrice->multiply(intdiv($line->quantity, $promotion->buyQuantity) * ($promotion->buyQuantity - $promotion->payQuantity)), $eligible),
            PromotionAction::AmountOff => ($promotion->amount()->isGreaterThan($subtotal) ? $subtotal : $promotion->amount())->allocate(...array_map(static fn (Money $each): int => $each->minorUnits, $worth)),
            default => $this->bundleDiscounts($promotion, $eligible, $left),
        };
        $discounts = [];

        foreach ($eligible as $index => $line) {
            $discounts[] = [$line, $amounts[$index]];
        }

        return $discounts;
    }

    /**
     * The discount of the complete bundles, spread over the units they take: each group takes its units from its
     * cheapest lines first, the earliest line first on a tie; each line's share is in proportion to what its units in
     * the bundles are worth, never more than what is left of it.
     *
     * @param list<OrderLine>      $eligible lines that fill some group
     * @param array<string, Money> $left
     *
     * @return list<Money> aligned with `$eligible`
     */
    private function bundleDiscounts(Promotion $promotion, array $eligible, array $left): array
    {
        $zero = Money::zero($promotion->currency);
        $groups = $promotion->groups();
        $bundles = \PHP_INT_MAX;

        foreach ($groups as $group) {
            $units = 0;

            foreach ($eligible as $line) {
                $units += $group->covers($line->reference->id) ? $line->quantity : 0;
            }

            $bundles = min($bundles, intdiv($units, $group->quantity));
        }

        $none = array_fill(0, \count($eligible), $zero);

        $taken = $none;

        foreach ($groups as $group) {
            $members = array_filter($eligible, static fn (OrderLine $line): bool => $group->covers($line->reference->id));
            uasort($members, static fn (OrderLine $first, OrderLine $second): int => $first->unitPrice->compare($second->unitPrice));
            $needed = $bundles * $group->quantity;

            foreach ($members as $index => $line) {
                $units = min($needed, $line->quantity);
                $needed -= $units;
                $worth = $line->unitPrice->multiply($units);
                $taken[$index] = $worth->isGreaterThan($left[$line->reference->value()]) ? $left[$line->reference->value()] : $worth;
            }
        }

        $discount = Money::sum($zero, ...$taken)->subtract($promotion->amount()->multiply($bundles));

        return $discount->isPositive() ? $discount->allocate(...array_map(static fn (Money $each): int => $each->minorUnits, $taken)) : $none;
    }

    /**
     * Every code the buyer entered, in uppercase as codes are stored, looked up once.
     *
     * @return list<PromotionCode>
     */
    private function enteredCodes(Order $order): array
    {
        $codes = [];

        foreach (array_unique(array_map(strtoupper(...), $order->codes())) as $entered) {
            $code = $this->promotions->findCode($entered);

            if ($code instanceof PromotionCode) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param list<PromotionCode> $entered
     */
    private function codeFor(Promotion $promotion, array $entered): ?PromotionCode
    {
        return array_find($entered, static fn (PromotionCode $code): bool => $code->promotion === $promotion->code && $code->isUsable());
    }
}
