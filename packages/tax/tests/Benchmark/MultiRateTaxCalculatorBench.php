<?php

declare(strict_types=1);

namespace Phil\Tax\Tests\Benchmark;

use Generator;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxRate;

/**
 * The order processor taxes every line again on every change to an order, so a cart of many lines runs the
 * calculator once per line, per change.
 *
 * @BeforeMethods("setUp")
 */
final class MultiRateTaxCalculatorBench
{
    private readonly MultiRateTaxCalculator $calculator;

    /** @var list<Money> */
    private array $lines = [];

    /** @var list<TaxRate> */
    private array $rates = [];

    public function __construct()
    {
        $this->calculator = new MultiRateTaxCalculator();
    }

    /**
     * @param array{lines: int, rates: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');
        $this->lines = [];

        for ($i = 0; $i < $params['lines']; ++$i) {
            $this->lines[] = Money::of(1_999 + $i * 137, $currency);
        }

        $all = [TaxRate::ofPercent('GST', '5'), TaxRate::ofPercent('QST', '9.975'), TaxRate::ofPercent('ECO', '1.5')];
        $this->rates = \array_slice($all, 0, $params['rates']);
    }

    /**
     * @ParamProviders("carts")
     */
    public function benchCalculate(): void
    {
        foreach ($this->lines as $line) {
            $this->calculator->calculate($line, $this->rates);
        }
    }

    /**
     * @ParamProviders("carts")
     */
    public function benchExtract(): void
    {
        foreach ($this->lines as $line) {
            $this->calculator->extract($line, $this->rates);
        }
    }

    /**
     * @return Generator<string, array{lines: int, rates: int}>
     */
    public function carts(): Generator
    {
        yield '1 line, 1 rate' => ['lines' => 1, 'rates' => 1];
        yield '1 line, 2 rates' => ['lines' => 1, 'rates' => 2];
        yield '20 lines, 2 rates' => ['lines' => 20, 'rates' => 2];
        yield '200 lines, 3 rates' => ['lines' => 200, 'rates' => 3];
    }
}
