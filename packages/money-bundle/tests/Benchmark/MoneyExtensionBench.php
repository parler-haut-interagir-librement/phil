<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Benchmark;

use Generator;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension;

/**
 * `format_money` runs once per price of a listing; the cold subject builds a new extension, hence a new ICU formatter,
 * for each price, which is what the per-locale cache of the extension saves.
 *
 * @BeforeMethods("setUp")
 */
final class MoneyExtensionBench
{
    private readonly MoneyExtension $extension;

    /** @var list<Money> */
    private array $prices = [];

    public function __construct()
    {
        $this->extension = new MoneyExtension();
    }

    /**
     * @param array{prices: int, locale: string} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');
        $this->prices = [];

        for ($i = 0; $i < $params['prices']; ++$i) {
            $this->prices[] = Money::of(1_250 + $i * 997, $currency);
        }

        // The first formatter is built here, as an earlier filter of the page would have.
        $this->extension->formatMoney($this->prices[0], $params['locale']);
    }

    /**
     * @ParamProviders("listings")
     *
     * @param array{prices: int, locale: string} $params
     */
    public function benchFormatMoney(array $params): void
    {
        foreach ($this->prices as $price) {
            $this->extension->formatMoney($price, $params['locale']);
        }
    }

    /**
     * @ParamProviders("listings")
     *
     * @Revs(10)
     *
     * @param array{prices: int, locale: string} $params
     */
    public function benchFormatMoneyCold(array $params): void
    {
        foreach ($this->prices as $price) {
            new MoneyExtension()->formatMoney($price, $params['locale']);
        }
    }

    /**
     * @return Generator<string, array{prices: int, locale: string}>
     */
    public function listings(): Generator
    {
        yield '1 price fr_CA' => ['prices' => 1, 'locale' => 'fr_CA'];
        yield '24 prices fr_CA' => ['prices' => 24, 'locale' => 'fr_CA'];
        yield '24 prices en' => ['prices' => 24, 'locale' => 'en'];
        yield '500 prices fr_CA' => ['prices' => 500, 'locale' => 'fr_CA'];
    }
}
