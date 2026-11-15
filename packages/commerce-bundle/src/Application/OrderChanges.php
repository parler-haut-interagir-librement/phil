<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Change\OrderChangeState;
use Phil\CommerceBundle\Domain\Contract\OrderChangeRepository;
use Phil\CommerceBundle\Domain\Exception\OrderChangeRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;

/**
 * Changes of a placed order, prepared, confirmed and versioned: a change is prepared with a preview of the total it
 * would give, without touching the order; confirmed, it reopens the order, sets its lines at the prices of the
 * moment, places it again and counts one more revision; the difference due is then the change's `difference()`, and
 * what the order lacks or holds beyond its total is `Payments::summary()`'s.
 *
 * - **A change is confirmed on the revision it was prepared on**: a change prepared before another one was
 *   confirmed is refused, and prepared again.
 * - **An order whose receipt is issued is not changed**: a credit note and a new order replace it.
 * - **The order moves through its workflow** when `OrderLifecycle` is given, so its guards and its events apply.
 *
 * Nothing is committed here: the application commits its unit of work.
 */
final readonly class OrderChanges
{
    public function __construct(
        private OrderChangeRepository $changes,
        private OrderModifier $modifier,
        private ReceiptRepository $receipts,
        private ?OrderLifecycle $lifecycle = null,
    ) {
    }

    /**
     * Prepares a change and its preview, computed on a copy of the order.
     *
     * @param list<OrderChangeLine> $lines
     */
    public function prepare(Order $order, array $lines, string $reason, string $preparedBy, PricingContext $context, DateTimeImmutable $at): OrderChange
    {
        $this->checkChangeable($order);
        $copy = clone $order;
        $copy->reopen();
        $this->apply($copy, $lines, $context);

        $change = OrderChange::prepare($order, $lines, $reason, $preparedBy, $at, $copy->total());
        $this->changes->addOrderChange($change);

        return $change;
    }

    /**
     * Applies the prepared change to the order, places it again, and counts its new revision.
     */
    public function confirm(OrderChange $change, Order $order, string $confirmedBy, PricingContext $context, DateTimeImmutable $at): OrderChange
    {
        if ($change->orderNumber !== $order->number) {
            throw OrderChangeRefused::otherOrder($change->id, $order->number);
        }

        $this->checkChangeable($order);

        if ($change->baseRevision !== $order->revision()) {
            throw OrderChangeRefused::stale($change->id, $order->number, $change->baseRevision, $order->revision());
        }

        // Checked before the order moves: a change already settled leaves the order as it was.
        if (OrderChangeState::Prepared !== $change->state()) {
            throw OrderChangeRefused::notPrepared($change->id, $change->state());
        }

        $this->lifecycle instanceof OrderLifecycle ? $this->lifecycle->reopen($order, $at, $confirmedBy) : $order->reopen();
        $this->apply($order, $change->lines(), $context);
        $this->modifier->place($order, $at, $confirmedBy);
        $change->confirm($order->recordRevision(), $order->total(), $confirmedBy, $at);

        return $change;
    }

    public function abandon(OrderChange $change, DateTimeImmutable $at): void
    {
        $change->abandon($at);
    }

    private function checkChangeable(Order $order): void
    {
        if (OrderState::Placed !== $order->state()) {
            throw OrderChangeRefused::notPlaced($order->number);
        }

        if ($this->receipts->receiptOf($order->number) instanceof Receipt) {
            throw OrderChangeRefused::receiptIssued($order->number);
        }
    }

    /**
     * @param list<OrderChangeLine> $lines
     */
    private function apply(Order $cart, array $lines, PricingContext $context): void
    {
        foreach ($lines as $line) {
            $existing = $cart->line($line->reference);

            if (!$existing instanceof OrderLine) {
                if (0 < $line->quantity) {
                    $this->modifier->add($cart, $line->reference, $line->quantity, $context);
                }
            } elseif (0 === $line->quantity) {
                $this->modifier->remove($cart, $line->reference);
            } else {
                $this->modifier->changeQuantity($cart, $line->reference, $line->quantity, $context);
            }
        }

        $this->modifier->recalculate($cart);
    }
}
