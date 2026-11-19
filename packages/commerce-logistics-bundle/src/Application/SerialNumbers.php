<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Repository\SerialNumberRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The serial number of each unit, kept with its stock: registered at the location that holds it, never more units
 * there than are on hand, moved with a transfer, and recorded against the order it leaves with. Nothing is committed
 * here.
 */
final readonly class SerialNumbers
{
    /** The most serial numbers one call registers, moves or ships. */
    public const int MAX_BATCH = 1_000;

    public function __construct(
        private SerialNumberRepository $serials,
        private StockRepository $stock,
    ) {
    }

    /**
     * Registers units on hand at a location: goods received, or found by a count.
     *
     * @param list<string> $serials
     */
    public function register(string $sku, string $location, array $serials, DateTimeImmutable $at): void
    {
        $level = $this->level($sku, $location);
        $this->checkBatch($serials);
        $inStock = $this->serials->serialsInStock($sku, $location) + \count($serials);

        if ($inStock > $level->onHand()) {
            throw StockRefused::serialsBeyondStock($sku, $location, $inStock, $level->onHand());
        }

        foreach ($serials as $serial) {
            if ($this->serials->findSerial($sku, $serial) instanceof SerialNumber) {
                throw StockRefused::serialKnown($sku, $serial);
            }

            $this->serials->addSerial(SerialNumber::of($level, $serial, $at));
        }
    }

    /**
     * Moves units in stock to another location, with the transfer that moved their stock.
     *
     * @param list<string> $serials
     */
    public function move(string $sku, string $from, string $to, array $serials): void
    {
        $target = $this->level($sku, $to);
        $this->checkBatch($serials);

        foreach ($serials as $serial) {
            $unit = $this->unit($sku, $serial);

            if ($unit->location() !== $from) {
                throw StockRefused::serialElsewhere($sku, $serial, $from);
            }

            $unit->move($target);
        }
    }

    /**
     * Records the units that left with an order.
     *
     * @param list<string> $serials
     */
    public function ship(string $orderNumber, string $sku, array $serials, DateTimeImmutable $at): void
    {
        $this->checkBatch($serials);

        foreach ($serials as $serial) {
            $this->unit($sku, $serial)->ship($orderNumber, $at);
        }
    }

    /**
     * Puts a unit that came back on a shelf again.
     */
    public function restock(string $sku, string $serial, string $location): void
    {
        $this->unit($sku, $serial)->restock($this->level($sku, $location));
    }

    public function find(string $sku, string $serial): ?SerialNumber
    {
        return $this->serials->findSerial($sku, $serial);
    }

    /**
     * @return list<SerialNumber> the units that left with the order
     */
    public function ofOrder(string $orderNumber): array
    {
        return $this->serials->serialsOfOrder($orderNumber);
    }

    private function unit(string $sku, string $serial): SerialNumber
    {
        return $this->serials->findSerial($sku, $serial) ?? throw StockRefused::serialUnknown($sku, $serial);
    }

    private function level(string $sku, string $location): StockLevel
    {
        return $this->stock->level($sku, $location) ?? throw StockRefused::notAvailable($sku, $location, 1, 0);
    }

    /**
     * @param list<string> $serials
     */
    private function checkBatch(array $serials): void
    {
        if ([] === $serials || \count($serials) > self::MAX_BATCH || \count(array_unique($serials)) !== \count($serials)) {
            throw InvalidValue::because(\sprintf('A call names 1 to %d serial numbers, each once.', self::MAX_BATCH));
        }
    }
}
