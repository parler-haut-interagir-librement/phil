<?php

declare(strict_types=1);

namespace Phil\Money\Tests\Benchmark;

use Generator;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Money is built from a decimal string at every price read, and allocated on every split payment or instalment plan.
 */
final readonly class MoneyBench
{
    private Currency $currency;

    private Money $amount;

    public function __construct()
    {
        $this->currency = Currency::of('CAD');
        $this->amount = Money::of(1_000_003, $this->currency);
    }

    /**
     * @ParamProviders("decimals")
     *
     * @param array{amount: string} $params
     */
    public function benchOfDecimal(array $params): void
    {
        Money::ofDecimal($params['amount'], $this->currency);
    }

    /**
     * @ParamProviders("ratios")
     *
     * @param array{ratios: list<int>} $params
     */
    public function benchAllocate(array $params): void
    {
        $this->amount->allocate(...$params['ratios']);
    }

    /**
     * @return Generator<string, array{amount: string}>
     */
    public function decimals(): Generator
    {
        yield 'short' => ['amount' => '12.50'];
        yield 'long' => ['amount' => '123456789012.34'];
    }

    /**
     * @return Generator<string, array{ratios: list<int>}>
     */
    public function ratios(): Generator
    {
        yield '3 parts' => ['ratios' => [1, 1, 1]];
        yield '12 instalments' => ['ratios' => array_fill(0, 12, 1)];
        yield '100 weighted' => ['ratios' => range(1, 100)];
    }
}
