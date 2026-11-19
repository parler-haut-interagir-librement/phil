<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceLogisticsBundle\Domain\Delivery\FulfilmentState;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;

/**
 * An order leaving in one parcel or several: each shipment names how many units of which lines leave, takes them
 * off the shelf from the order's reservations, and keeps the carrier and the tracking number.
 */
final readonly class Shipments
{
    public function __construct(
        private Stock $stock,
        private ShipmentRepository $shipments,
    ) {
    }

    /**
     * @param array<string, int> $lines units by order line, as `type:id`; every unit left by default
     */
    public function ship(Order $order, DateTimeImmutable $at, ?array $lines = null, ?string $carrier = null, ?string $tracking = null): Shipment
    {
        if (OrderState::Placed !== $order->state()) {
            throw StockRefused::notShippable($order->number);
        }

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

        $shipment = Shipment::of($order->number, $lines, $at, $carrier, $tracking);

        foreach ($order->lines() as $orderLine) {
            $reference = $orderLine->reference;

            if ('product' === $reference->type && isset($lines[$reference->value()])) {
                $this->stock->shipPart($order->number, $reference->id, $lines[$reference->value()], $at);
            }
        }

        $this->shipments->addShipment($shipment);

        return $shipment;
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
}
