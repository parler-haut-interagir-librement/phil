<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\CommerceLogisticsBundle\Application\Costing;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostingMethod;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostShare;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Units leaving a level whose cost is kept, under both methods: a weighted average reads one pool, first in, first out
 * walks the layers from the oldest, so its cost grows with the layers a shipment empties. A landed cost is shared over
 * the lines of a receipt by one `Money::allocate()`, linear in the lines.
 *
 * The in-memory repository finds layers by scanning them, where Doctrine runs one indexed query per SKU and location.
 *
 * @BeforeMethods("setUp")
 */
final class CostingBench
{
    private InMemoryLogistics $repository;

    private Costing $costing;

    private readonly DateTimeImmutable $at;

    private readonly Currency $cad;

    /** @var list<array{string, int, int}> */
    private array $lines = [];

    public function __construct()
    {
        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $this->cad = Currency::of('CAD');
        $this->repository = new InMemoryLogistics();
        $this->costing = new Costing($this->repository, CostingMethod::WeightedAverage, $this->cad);
    }

    /**
     * @param array{method: string, layers: int} $params
     */
    public function setUp(array $params): void
    {
        $this->repository = new InMemoryLogistics();
        $this->costing = new Costing($this->repository, CostingMethod::from($params['method']), $this->cad);

        for ($layer = 0; $layer < $params['layers']; ++$layer) {
            $this->costing->receive('DICE', 'warehouse', 10, Money::of(1_000 + $layer, $this->cad), null, 'PO-'.$layer, $this->at->modify('+'.$layer.' minutes'));
        }

        $this->lines = [];

        for ($line = 0; $line < $params['layers']; ++$line) {
            $this->lines[] = ['SKU-'.$line, 1_000 * ($line + 1), 3];
        }
    }

    /**
     * One shipment takes every unit of the level but one; each iteration starts from the layers of `setUp()`.
     *
     * @Revs(1)
     *
     * @Iterations(20)
     *
     * @ParamProviders("levels")
     *
     * @param array{method: string, layers: int} $params
     */
    public function benchIssue(array $params): void
    {
        $units = 10 * $params['layers'] - 1;
        $this->costing->issue('DICE', 'warehouse', $units, $units + 1, 'BENCH-1', $this->at);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @ParamProviders("levels")
     */
    public function benchLandedCost(): void
    {
        CostShare::landed(Money::of(99_999, $this->cad), $this->lines, 'PO-1');
    }

    /**
     * @return Generator<string, array{method: string, layers: int}>
     */
    public function levels(): Generator
    {
        foreach (['weighted_average', 'fifo'] as $method) {
            foreach ([1, 10, 100] as $layers) {
                yield $method.' × '.$layers => ['method' => $method, 'layers' => $layers];
            }
        }
    }
}
