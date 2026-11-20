<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The stock of one SKU at one location: what is on hand, and how much of it is reserved for orders.
 *
 * **Available is on hand less reserved**, never below zero for a reservation; a count may find less on hand than is
 * reserved, which the application then settles. A version column refuses two movements made over the same copy.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_stock_level')]
final class StockLevel
{
    #[ORM\Column(name: 'on_hand', type: 'integer')]
    private int $onHand = 0;

    #[ORM\Column(name: 'reserved', type: 'integer')]
    private int $reserved = 0;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Id]
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
    ) {
    }

    public static function of(string $sku, Location $location): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
        }

        return new self($sku, $location->code);
    }

    public function onHand(): int
    {
        return $this->onHand;
    }

    public function reserved(): int
    {
        return $this->reserved;
    }

    public function available(): int
    {
        return max(0, $this->onHand - $this->reserved);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function receive(int $quantity): void
    {
        $this->onHand += $this->checked($quantity);
    }

    /**
     * Takes units off the shelf without an order: a transfer out.
     */
    public function remove(int $quantity): void
    {
        if ($this->checked($quantity) > $this->available()) {
            throw StockRefused::notAvailable($this->sku, $this->location, $quantity, $this->available());
        }

        $this->onHand -= $quantity;
    }

    /**
     * Sets what a count found on hand, and returns the difference with what was recorded.
     */
    public function count(int $counted): int
    {
        if ($counted < 0) {
            throw InvalidValue::because(\sprintf('A count finds zero units or more; %d is not.', $counted));
        }

        $difference = $counted - $this->onHand;
        $this->onHand = $counted;

        return $difference;
    }

    public function reserve(int $quantity): void
    {
        if ($this->checked($quantity) > $this->available()) {
            throw StockRefused::notAvailable($this->sku, $this->location, $quantity, $this->available());
        }

        $this->reserved += $quantity;
    }

    /**
     * Reserves units beyond what is available: a SKU sold without stock, its shortfall held until goods arrive.
     */
    public function backorder(int $quantity): void
    {
        $this->reserved += $this->checked($quantity);
    }

    /**
     * The reserved units not on hand: what a backorder still waits for.
     */
    public function backordered(): int
    {
        return max(0, $this->reserved - $this->onHand);
    }

    public function release(int $quantity): void
    {
        $this->reserved -= min($this->checked($quantity), $this->reserved);
    }

    /**
     * Reserved units leave with their order; a unit not on hand — one still backordered — cannot leave.
     */
    public function ship(int $quantity): void
    {
        if ($this->checked($quantity) > $this->onHand) {
            throw StockRefused::notOnHand($this->sku, $this->location, $quantity, $this->onHand);
        }

        $this->reserved -= min($quantity, $this->reserved);
        $this->onHand -= $quantity;
    }

    private function checked(int $quantity): int
    {
        if ($quantity < 1) {
            throw StockRefused::quantity($quantity);
        }

        return $quantity;
    }
}
