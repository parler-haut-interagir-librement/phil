<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * The cart at the register: parked and picked up again, tipped, and rung up in the session.
 *
 * The cart itself is the core's order, built with its `OrderModifier`; the register adds what only a till does.
 */
final readonly class RegisterCheckout
{
    private const int PARTS = 1_000_000;

    public function __construct(
        private OrderModifier $modifier,
        private OrderRepository $orders,
        private Registers $registers,
        private RegisterSaleRepository $sales,
        private string $tipLabel,
    ) {
    }

    /**
     * Prices a cart as the session sells, taxes included in the prices or added to them, and recomputes it: the cashier
     * starts each cart of the session with it, before the customer sees a total.
     */
    public function start(Order $order, CashSession $session): void
    {
        $this->checkCart($order);

        if (!$session->isOpen()) {
            throw PosRefused::sessionClosed($session->id);
        }

        $this->modifier->changeTaxIncluded($order, $session->taxIncluded);
    }

    /**
     * Parks a stored cart at a register under a label.
     */
    public function hold(Order $order, string $register, string $label, string $by, DateTimeImmutable $at): HeldSale
    {
        $this->checkCart($order);
        $held = HeldSale::of($order->number, $this->registers->get($register), $label, $by, $at);
        $this->sales->hold($held);

        return $held;
    }

    /**
     * Picks a held cart up again at a register: the one that held it, or the main register of its location — a sale a
     * seller started on a phone, finished at the counter. It takes the network: the server holds the cart.
     */
    public function resume(string $orderNumber, string $register): Order
    {
        $held = $this->sales->findHeld($orderNumber) ?? throw PosRefused::notHeld($orderNumber);
        $till = $this->registers->get($register);

        if ($held->register !== $till->code) {
            $origin = $this->registers->get($held->register);

            if (!$till->isMain() || $origin->location() !== $till->location()) {
                throw PosRefused::notResumableHere($orderNumber, $till->code);
            }
        }

        $order = $this->orders->find($orderNumber) ?? throw PosRefused::notHeld($orderNumber);
        $this->sales->release($held);

        return $order;
    }

    /**
     * @return list<HeldSale>
     */
    public function held(string $register): array
    {
        return $this->sales->heldAt($register);
    }

    /**
     * Sets the tip, replacing any other; zero removes it.
     */
    public function tip(Order $order, Money $amount): void
    {
        $this->checkCart($order);

        if ($amount->isNegative()) {
            throw InvalidValue::because('A tip is zero or more.');
        }

        $order->replaceAdjustments(AdjustmentType::Tip, $amount->isZero() ? [] : [Adjustment::of(AdjustmentType::Tip, $this->tipLabel, $amount)]);
    }

    /**
     * Sets the tip as a share of the items, before taxes, in parts per million: 150 000 for 15 %. When the prices
     * include the taxes, the share is of the items less those taxes.
     */
    public function tipShare(Order $order, int $share): Money
    {
        if ($share < 0 || $share > self::PARTS) {
            throw InvalidValue::because(\sprintf('A tip is 0 to 1000000 parts per million of the items; %d is refused.', $share));
        }

        $items = $order->itemsTotal()->subtract($order->includedTax());
        $tip = Money::of(intdiv($items->minorUnits * $share + self::PARTS / 2, self::PARTS), $items->currency);
        $this->tip($order, $tip);

        return $tip;
    }

    /**
     * Places the cart as a sale of the session: the core recomputes it one last time, the tip kept. A cart sold under a
     * preset that identifies the customer is rung up once it has one.
     */
    public function ring(Order $order, CashSession $session, string $by, DateTimeImmutable $at, ?SalePreset $preset = null): RegisterSale
    {
        $this->checkCart($order);

        if (!$session->isOpen()) {
            throw PosRefused::sessionClosed($session->id);
        }

        if ($order->taxIncluded() !== $session->taxIncluded) {
            throw PosRefused::taxModeDiffers($order->number, $session->id);
        }

        if ($preset instanceof SalePreset && $preset->identifiesCustomer() && null === $order->customer()) {
            throw PosRefused::customerRequired($order->number);
        }

        $this->modifier->place($order, $at);
        $sale = RegisterSale::of($order->number, $session, $by, $at, $order->adjustmentsTotal(AdjustmentType::Tip), $preset?->code);
        $this->sales->addSale($sale);

        return $sale;
    }

    private function checkCart(Order $order): void
    {
        if (OrderState::Cart !== $order->state()) {
            throw PosRefused::notACart($order->number);
        }
    }
}
