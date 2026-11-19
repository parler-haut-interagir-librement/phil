<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Symfony\Component\Uid\Uuid;

/**
 * Units of a SKU set aside at one location for one order, until they are shipped or given back.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_reservation')]
#[ORM\Index(columns: ['order_number'])]
final class Reservation
{
    #[ORM\Column(name: 'state', length: 16, enumType: ReservationState::class)]
    private ReservationState $state = ReservationState::Held;

    #[ORM\Column(name: 'shipped', type: 'integer')]
    private int $shipped = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'quantity', type: 'integer')]
        public readonly int $quantity,
    ) {
    }

    public static function hold(string $orderNumber, StockLevel $level, int $quantity): self
    {
        $level->reserve($quantity);

        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $level->sku, $level->location, $quantity);
    }

    /**
     * Holds units beyond what the level has available: a SKU sold without stock. The units leave once received.
     */
    public static function backorder(string $orderNumber, StockLevel $level, int $quantity): self
    {
        $level->backorder($quantity);

        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $level->sku, $level->location, $quantity);
    }

    public function state(): ReservationState
    {
        return $this->state;
    }

    /**
     * What is still held: the reservation less what already left.
     */
    public function remaining(): int
    {
        return $this->quantity - $this->shipped;
    }

    /**
     * Gives back what is still held.
     */
    public function release(StockLevel $level): void
    {
        $this->checkHeld();
        $level->release($this->remaining());
        $this->state = ReservationState::Released;
    }

    /**
     * Sends what is still held.
     */
    public function ship(StockLevel $level): void
    {
        $this->shipPart($level, $this->remaining());
    }

    /**
     * Sends part of what is held: a partial shipment. Once nothing is left, the reservation is shipped.
     */
    public function shipPart(StockLevel $level, int $quantity): void
    {
        $this->checkHeld();

        if ($quantity < 1 || $quantity > $this->remaining()) {
            throw StockRefused::quantity($quantity);
        }

        $level->ship($quantity);
        $this->shipped += $quantity;

        if (0 === $this->remaining()) {
            $this->state = ReservationState::Shipped;
        }
    }

    private function checkHeld(): void
    {
        if (ReservationState::Held !== $this->state) {
            throw StockRefused::notHeld($this->id);
        }
    }
}
