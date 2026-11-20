<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Every placed order is reserved once, line by line across the open locations by priority; the cost grows with the
 * order's lines times the locations holding each SKU, and the non-product lines go through the same loop.
 *
 * The in-memory repository finds levels by scanning them, where Doctrine runs one `IN` query: its share grows with
 * the levels times the SKUs asked and is part of the numbers.
 *
 * @BeforeMethods("setUp")
 */
final class StockBench
{
    private readonly InMemoryLogistics $repository;

    private readonly Stock $stock;

    private Order $order;

    private readonly DateTimeImmutable $at;

    public function __construct()
    {
        $this->repository = new InMemoryLogistics();
        $this->stock = new Stock($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-03T10:00:00Z');
        $this->order = Order::open('BENCH-1', Currency::of('CAD'));
    }

    /**
     * @param array{lines: int, locations: int} $params
     */
    public function setUp(array $params): void
    {
        for ($location = 0; $location < $params['locations']; ++$location) {
            $this->repository->addLocation(Location::of('location-'.$location, 'Location '.$location, $location));
        }

        $cad = Currency::of('CAD');
        $price = Money::of(1_000, $cad);
        $this->order = Order::open('BENCH-1', $cad);

        for ($line = 0; $line < $params['lines']; ++$line) {
            $sku = 'SKU-'.$line;

            // Enough stock everywhere that no rev runs short; the highest-priority location holds only one unit,
            // so each line is taken from two levels.
            for ($location = 0; $location < $params['locations']; ++$location) {
                $this->stock->receive($sku, 'location-'.$location, $params['locations'] - 1 === $location ? 1 : 1_000_000, $this->at);
            }

            // One line in five is not a product: an offer, a gift card.
            $type = 4 === $line % 5 ? 'offer' : 'product';
            $this->order->add(Sellable::of(SellableReference::of($type, $sku), $sku, $price, 'goods'), 2, $price);
        }

        $this->order->place($this->at);
        $this->repository->movements = [];
    }

    /**
     * Each rev forgets the reservations of the one before, so the same order is reserved again rather than skipped.
     *
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @ParamProviders("orders")
     */
    public function benchReserve(): void
    {
        $this->repository->reservations = [];
        $this->repository->movements = [];

        $this->stock->reserve($this->order, $this->at);
    }

    /**
     * @return Generator<string, array{lines: int, locations: int}>
     */
    public function orders(): Generator
    {
        yield '5 lines, 2 locations' => ['lines' => 5, 'locations' => 2];
        yield '20 lines, 5 locations' => ['lines' => 20, 'locations' => 5];
        yield '100 lines, 10 locations' => ['lines' => 100, 'locations' => 10];
    }
}
