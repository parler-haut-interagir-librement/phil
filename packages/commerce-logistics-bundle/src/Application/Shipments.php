<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentForcing;
use Phil\CommerceLogisticsBundle\Domain\Delivery\FulfilmentState;
use Phil\CommerceLogisticsBundle\Domain\Event\ShipmentForced;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An order leaving in one parcel or several: each shipment names how many units of which lines leave, takes them
 * off the shelf from the order's reservations, and keeps the carrier and the tracking number.
 *
 * - **An order leaves once authorized**: the `ShipmentAuthorization` answers, by default once the payments cover what
 *   the order is due; an unpaid order, or one with a deposit only, stays.
 * - **A forced shipment needs a permission**: `force()` ships what no authorization lets leave, for whoever
 *   `ShipmentForcing` allows — nobody by default —, keeps who forced it and why on the shipment, and announces it by
 *   `ShipmentForced`.
 */
final readonly class Shipments
{
    public function __construct(
        private Stock $stock,
        private ShipmentRepository $shipments,
        private ShipmentAuthorization $authorization,
        private ShipmentForcing $forcing = new DenyShipmentForcing(),
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * Ships an order the authorization lets leave.
     *
     * @param array<string, int>|null $lines units by order line, as `type:id`; every unit left by default
     *
     * @throws StockRefused when the order is not placed or not authorized, or the lines are not left to ship
     */
    public function ship(Order $order, DateTimeImmutable $at, ?array $lines = null, ?string $carrier = null, ?string $tracking = null): Shipment
    {
        $this->checkPlaced($order);

        if (!$this->authorization->authorizes($order)) {
            throw StockRefused::notAuthorized($order->number);
        }

        return $this->leave($order, Shipment::of($order->number, $this->linesOf($order, $lines), $at, $carrier, $tracking), $at);
    }

    /**
     * Ships an order whatever the authorization says, for whoever `ShipmentForcing` allows; the shipment keeps who
     * forced it and why, and `ShipmentForced` announces it once kept.
     *
     * @param string                  $actor  the stable identifier of who forces the shipment
     * @param string                  $reason why, on one line: 1 to 255 characters
     * @param array<string, int>|null $lines  units by order line, as `type:id`; every unit left by default
     *
     * @throws StockRefused when the actor may not force it, the order is not placed, or the lines are not left to ship
     */
    public function force(Order $order, string $actor, string $reason, DateTimeImmutable $at, ?array $lines = null, ?string $carrier = null, ?string $tracking = null): Shipment
    {
        $this->checkPlaced($order);

        if (!$this->forcing->allows($order, $actor)) {
            throw StockRefused::forcingRefused($order->number);
        }

        $shipment = $this->leave($order, Shipment::forced($order->number, $this->linesOf($order, $lines), $at, $actor, $reason, $carrier, $tracking), $at);
        $this->dispatcher?->dispatch(new ShipmentForced($shipment->id, $order->number, $actor, $at));

        return $shipment;
    }

    /**
     * Whether the order may leave now, without forcing it: what a page asks before it offers to ship.
     */
    public function isAuthorized(Order $order): bool
    {
        return OrderState::Placed === $order->state() && $this->authorization->authorizes($order);
    }

    /**
     * @return array<string, int> the units of each line not shipped yet
     */
    public function left(Order $order): array
    {
        $left = [];

        foreach ($order->lines() as $line) {
            $left[$line->reference->value()] = $line->quantity;
        }

        foreach ($this->shipments->shipmentsOf($order->number) as $shipment) {
            foreach ($shipment->lines as $line => $quantity) {
                if (isset($left[$line])) {
                    $left[$line] -= $quantity;
                }
            }
        }

        return $left;
    }

    public function state(Order $order): FulfilmentState
    {
        $left = array_sum($this->left($order));
        $ordered = array_sum(array_map(static fn (OrderLine $line): int => $line->quantity, $order->lines()));

        return match (true) {
            0 === $left => FulfilmentState::Shipped,
            $left === $ordered => FulfilmentState::Unshipped,
            default => FulfilmentState::Partial,
        };
    }

    private function checkPlaced(Order $order): void
    {
        if (OrderState::Placed !== $order->state()) {
            throw StockRefused::notShippable($order->number);
        }
    }

    /**
     * @param array<string, int>|null $lines
     *
     * @return array<string, int> the lines asked, each within what is left to ship
     */
    private function linesOf(Order $order, ?array $lines): array
    {
        $left = $this->left($order);
        $lines ??= array_filter($left, static fn (int $quantity): bool => $quantity > 0);

        foreach ($lines as $line => $quantity) {
            if (!\array_key_exists($line, $left)) {
                throw StockRefused::notInOrder($order->number, $line);
            }

            if ($quantity > $left[$line]) {
                throw StockRefused::overShipped($line, $quantity, $left[$line]);
            }
        }

        return $lines;
    }

    /**
     * Takes the shipment's units off the shelf, then keeps it.
     */
    private function leave(Order $order, Shipment $shipment, DateTimeImmutable $at): Shipment
    {
        foreach ($order->lines() as $orderLine) {
            $reference = $orderLine->reference;

            if ('product' === $reference->type && isset($shipment->lines[$reference->value()])) {
                $this->stock->shipPart($order->number, $reference->id, $shipment->lines[$reference->value()], $at);
            }
        }

        $this->shipments->addShipment($shipment);

        return $shipment;
    }
}
