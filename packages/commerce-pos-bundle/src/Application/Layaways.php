<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * Layaways: a placed order put aside once its deposit is paid, then paid in instalments — payments of the core —,
 * completed when nothing is left to pay, or cancelled.
 *
 * Holding the goods is the stock's affair: a reservation of `phil/commerce-logistics-bundle`, if the application uses
 * it. Refunding the instalments of a cancelled layaway is the application's decision.
 */
final readonly class Layaways
{
    private const int PARTS = 1_000_000;

    /**
     * @param int $deposit the share of the order paid before it is put aside, in parts per million
     */
    public function __construct(
        private Payments $payments,
        private RegisterSaleRepository $sales,
        private int $deposit,
    ) {
        if ($deposit < 0 || $deposit > self::PARTS) {
            throw InvalidValue::because(\sprintf('A deposit is 0 to 1000000 parts per million; %d is refused.', $deposit));
        }
    }

    /**
     * What must be paid before the order is put aside.
     */
    public function depositFor(Order $order): Money
    {
        $total = $order->total();

        return Money::of(intdiv($total->minorUnits * $this->deposit + self::PARTS - 1, self::PARTS), $total->currency);
    }

    /**
     * Puts a placed order aside, once its deposit is paid. An order is put aside once: a second call is refused, as
     * is a cart, whose total may still change, or a cancelled order.
     */
    public function open(Order $order, string $customer, DateTimeImmutable $dueAt, DateTimeImmutable $at): Layaway
    {
        if (OrderState::Placed !== $order->state()) {
            throw PosRefused::notPlacedForLayaway($order->number);
        }

        if ($this->sales->findLayaway($order->number) instanceof Layaway) {
            throw PosRefused::alreadyALayaway($order->number);
        }

        $deposit = $this->depositFor($order);
        $paid = $this->payments->summary($order)->captured();

        if ($paid->isLessThan($deposit)) {
            throw PosRefused::depositShort($order->number, $paid->toDecimal(), $deposit->toDecimal());
        }

        $layaway = Layaway::of($order->number, $customer, $at, $dueAt);
        $this->sales->addLayaway($layaway);

        return $layaway;
    }

    /**
     * Completes the layaway once nothing is left to pay; `true` when it did.
     */
    public function settle(Order $order, DateTimeImmutable $at): bool
    {
        $layaway = $this->get($order->number);

        if (!$this->payments->summary($order)->payable()->isZero()) {
            return false;
        }

        $layaway->complete($at);

        return true;
    }

    /**
     * Gives the layaway up, and cancels its order.
     */
    public function cancel(Order $order, DateTimeImmutable $at): void
    {
        $this->get($order->number)->cancel($at);
        $order->cancel($at);
    }

    /**
     * @return list<Layaway> the open layaways due by then
     */
    public function due(DateTimeImmutable $at): array
    {
        return $this->sales->layawaysDue($at);
    }

    private function get(string $orderNumber): Layaway
    {
        $layaway = $this->sales->findLayaway($orderNumber);

        if (!$layaway instanceof Layaway || LayawayState::Open !== $layaway->state()) {
            throw PosRefused::notALayaway($orderNumber);
        }

        return $layaway;
    }
}
