<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceReturnsBundle\Application\Contract\SetParts;
use Phil\CommerceReturnsBundle\Application\Contract\SpecialDiscounts;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;

/**
 * What a return of an order takes back and is worth, before the goods come back: its lines, the discounts of the
 * specials it breaks, and the delivery charge it gives back.
 *
 * - **A line** is worth its share of what the order line came to, promotions and taxes included — the discount of a
 *   special and the tax it saved put back, since the special's discount is charged apart.
 * - **A set** comes back whole: a request naming one of its parts is refused.
 * - **A special** — a price for products bought together — loses its discount as soon as one of its products comes
 *   back: each return is charged the part of the discount, taxes included, that the units back so far break, the
 *   share of the most returned of its lines, less what earlier returns were charged. Returning every product of a
 *   special gives back what was paid, and no more.
 * - **The delivery charge**, on no line, is given back once per order, as the policy says.
 *
 * Each order line, adjustment and holding return is read once per request.
 */
final readonly class ReturnValuation
{
    public function __construct(
        private ?SetParts $sets = null,
        private ?SpecialDiscounts $specials = null,
    ) {
    }

    /**
     * @param list<array{line: string, quantity: int, reason: ReturnReason}> $asked   the order lines as `type:id`
     * @param list<ReturnRequest>                                            $holding the order's returns still holding units
     *
     * @return array{lines: list<ReturnLine>, specialCharges: array<string, int>, deliveryRefund: int}
     */
    public function value(Order $order, array $asked, array $holding, DeliveryRefund $policy): array
    {
        $held = [];
        $charged = [];
        $deliveryTaken = false;

        foreach ($holding as $return) {
            foreach ($return->lines() as $returned) {
                $held[$returned->line] = ($held[$returned->line] ?? 0) + $returned->quantity;
            }

            foreach ($return->specialCharges() as $origin => $charge) {
                $charged[$origin] = ($charged[$origin] ?? 0) + $charge;
            }

            $deliveryTaken = $deliveryTaken || $return->deliveryRefund()->isPositive();
        }

        $orderLines = [];

        foreach ($order->lines() as $orderLine) {
            $orderLines[$orderLine->reference->value()] = $orderLine;
        }

        $adjustments = $order->adjustmentsByLine();
        [$effects, $specials] = $this->specials($orderLines, $adjustments);
        $lines = [];
        $back = [];
        $shopsFault = true;

        foreach ($asked as $request) {
            $line = $orderLines[$request['line']] ?? throw $this->missing($order, $orderLines, $request['line']);
            $left = $line->quantity - ($held[$request['line']] ?? 0);

            if ($request['quantity'] > $left) {
                throw ReturnRefused::tooMany($request['line'], $request['quantity'], $left);
            }

            $paid = $this->paid($line, $adjustments[$request['line']] ?? []) + ($effects[$request['line']] ?? 0);
            $lines[] = ReturnLine::of($request['line'], $request['quantity'], $request['reason'], $paid, $line->quantity, $line->quantity - $left);
            $back[$request['line']] = $request['quantity'];
            $shopsFault = $shopsFault && $request['reason']->isShopsFault();
        }

        $wholeOrder = [] === array_filter($orderLines, static fn (OrderLine $line, string $key): bool => ($held[$key] ?? 0) + ($back[$key] ?? 0) < $line->quantity, \ARRAY_FILTER_USE_BOTH);
        $delivery = !$deliveryTaken && $policy->refunds($shopsFault, $wholeOrder) ? max(0, $this->delivery($order)) : 0;

        return [
            'lines' => $lines,
            'specialCharges' => $this->charges($specials, $orderLines, $held, $back, $charged, array_sum(array_map(static fn (ReturnLine $line): int => $line->value(), $lines))),
            'deliveryRefund' => $delivery,
        ];
    }

    /**
     * What each special's discount saved on each of its lines, taxes included, and the specials by origin.
     *
     * @param array<string, OrderLine>                  $orderLines
     * @param array<string, non-empty-list<Adjustment>> $adjustments
     *
     * @return array{array<string, int>, array<string, array<string, int>>} line → what its specials saved; origin → line → saved
     */
    private function specials(array $orderLines, array $adjustments): array
    {
        $effects = [];
        $specials = [];

        if (!$this->specials instanceof SpecialDiscounts) {
            return [$effects, $specials];
        }

        foreach ($orderLines as $key => $line) {
            $own = $adjustments[$key] ?? [];
            [$base, $taxes] = $this->taxBase($line, $own);

            foreach ($own as $adjustment) {
                if (AdjustmentType::Promotion !== $adjustment->type || $adjustment->neutral || null === $adjustment->origin || !$adjustment->amount->isNegative() || !$this->specials->isSpecial($adjustment)) {
                    continue;
                }

                $discount = -$adjustment->amount->minorUnits;
                // The tax the discount saved, halves up: a special that disappears takes it back too.
                $saved = $discount + ($base > 0 ? intdiv(2 * $taxes * $discount + $base, 2 * $base) : 0);
                $effects[$key] = ($effects[$key] ?? 0) + $saved;
                $specials[$adjustment->origin][$key] = ($specials[$adjustment->origin][$key] ?? 0) + $saved;
            }
        }

        return [$effects, $specials];
    }

    /**
     * What each special this return breaks further is charged: the share of its discount the most returned of its
     * lines reaches, halves up, less what earlier returns were charged; all of them no more than the lines are worth.
     *
     * @param array<string, array<string, int>> $specials   origin → line → saved
     * @param array<string, OrderLine>          $orderLines
     * @param array<string, int>                $held       units earlier returns hold, by line
     * @param array<string, int>                $back       units this return takes back, by line
     * @param array<string, int>                $charged    what earlier returns were charged, by origin
     *
     * @return array<string, int>
     */
    private function charges(array $specials, array $orderLines, array $held, array $back, array $charged, int $worth): array
    {
        $charges = [];

        foreach ($specials as $origin => $lines) {
            $discount = array_sum($lines);
            $target = 0;

            foreach (array_keys($lines) as $key) {
                $quantity = $orderLines[$key]->quantity;
                $target = max($target, intdiv(2 * $discount * (($held[$key] ?? 0) + ($back[$key] ?? 0)) + $quantity, 2 * $quantity));
            }

            $charge = min($target - ($charged[$origin] ?? 0), $worth);

            if ($charge > 0) {
                $charges[$origin] = $charge;
                $worth -= $charge;
            }
        }

        return $charges;
    }

    /**
     * What a line came to: its subtotal and the adjustments on it that count.
     *
     * @param list<Adjustment> $adjustments
     */
    private function paid(OrderLine $line, array $adjustments): int
    {
        $paid = $line->subtotal()->minorUnits;

        foreach ($adjustments as $adjustment) {
            if (!$adjustment->neutral) {
                $paid += $adjustment->amount->minorUnits;
            }
        }

        return $paid;
    }

    /**
     * The base a line was taxed on — its subtotal with its options, promotions and proration — and the taxes added on
     * it; taxes already in the price add nothing.
     *
     * @param list<Adjustment> $adjustments
     *
     * @return array{int, int}
     */
    private function taxBase(OrderLine $line, array $adjustments): array
    {
        $base = $line->subtotal()->minorUnits;
        $taxes = 0;

        foreach ($adjustments as $adjustment) {
            if ($adjustment->neutral) {
                continue;
            }

            if (AdjustmentType::Tax === $adjustment->type) {
                $taxes += $adjustment->amount->minorUnits;
            } elseif (\in_array($adjustment->type, [AdjustmentType::Option, AdjustmentType::Promotion, AdjustmentType::Proration], true)) {
                $base += $adjustment->amount->minorUnits;
            }
        }

        return [$base, $taxes];
    }

    /**
     * The delivery charge on no line, its waivers included.
     */
    private function delivery(Order $order): int
    {
        $delivery = 0;

        foreach ($order->adjustments(AdjustmentType::Shipping) as $adjustment) {
            if (null === $adjustment->line && !$adjustment->neutral) {
                $delivery += $adjustment->amount->minorUnits;
            }
        }

        return $delivery;
    }

    /**
     * Why a line the order lacks cannot come back: a part of one of its sets, or no line of it at all.
     *
     * @param array<string, OrderLine> $orderLines
     */
    private function missing(Order $order, array $orderLines, string $asked): ReturnRefused
    {
        if ($this->sets instanceof SetParts) {
            foreach ($orderLines as $key => $line) {
                if (\in_array($asked, $this->sets->partsOf($line->reference->unconfigured()), true)) {
                    return ReturnRefused::partOfSet($order->number, $asked, $key);
                }
            }
        }

        return ReturnRefused::notInOrder($order->number, $asked);
    }
}
