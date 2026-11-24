<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Benchmark;

use Generator;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\MarginCalculator;
use Phil\Pricing\PriceCalculator;
use Phil\Pricing\PricedLine;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\PriceEndingBands;
use Phil\Pricing\Rate;
use Phil\Pricing\SuggestedRetailPrice;
use Phil\Pricing\WholesalePriceList;

/**
 * A cart prices and discounts every line again on each change; a margin report sums every line of a period; a
 * catalogue import prices and ends every product from its cost, and suggests a retail price for each with its wholesale
 * tiers.
 *
 * @BeforeMethods("setUp")
 */
final class PricingBench
{
    private readonly MarginCalculator $margins;

    private readonly PriceCalculator $prices;

    private readonly DiscountCascade $cascade;

    private readonly PriceEndingBands $endings;

    /** @var list<PricedLine> */
    private array $lines = [];

    /** @var list<Money> */
    private array $costs = [];

    /** @var list<Rate> */
    private readonly array $discounts;

    public function __construct()
    {
        $this->margins = new MarginCalculator();
        $this->prices = new PriceCalculator();
        $this->cascade = new DiscountCascade();
        $currency = Currency::of('CAD');
        $this->endings = PriceEndingBands::startingWith(PriceEnding::ofDecimals('1', '0.99', $currency))
            ->from(Money::ofDecimal('10', $currency), PriceEnding::ofDecimals('1', '0.95', $currency))
            ->from(Money::ofDecimal('100', $currency), PriceEnding::ofDecimals('1', '0', $currency));
        $this->discounts = [Rate::ofPercent('20'), Rate::ofPercent('10'), Rate::ofPercent('5')];
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = Currency::of('CAD');
        $this->lines = [];
        $this->costs = [];

        for ($i = 0; $i < $params['lines']; ++$i) {
            $cost = Money::of(600 + $i * 37, $currency);
            $this->costs[] = $cost;
            $this->lines[] = PricedLine::of(Money::of(1_000 + $i * 61, $currency), $cost, 1 + $i % 4);
        }
    }

    /**
     * @ParamProviders("sizes")
     */
    public function benchSummarize(): void
    {
        $this->margins->summarize(...$this->lines)->marginRate();
    }

    /**
     * @ParamProviders("sizes")
     */
    public function benchCascade(): void
    {
        foreach ($this->lines as $line) {
            $this->cascade->apply($line->price, $this->discounts);
        }
    }

    /**
     * @ParamProviders("sizes")
     */
    public function benchPriceAndEnd(): void
    {
        $margin = Rate::ofPercent('35');

        foreach ($this->costs as $cost) {
            $this->endings->apply($this->prices->priceForMargin($cost, $margin), $cost);
        }
    }

    /**
     * @ParamProviders("sizes")
     */
    public function benchWholesaleAndSuggest(): void
    {
        $markup = Rate::ofPercent('30');
        $tierMarkup = Rate::ofPercent('25');
        $keystone = Rate::ofPercent('100');

        foreach ($this->costs as $cost) {
            $wholesale = WholesalePriceList::fromCost($cost, $markup)->fromMarkup(12, $tierMarkup);
            $wholesale->unitPriceFor(24);
            SuggestedRetailPrice::fromWholesale($wholesale->basePrice(), $keystone)->ended($this->endings)->compare($cost);
        }
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function sizes(): Generator
    {
        yield '1 line' => ['lines' => 1];
        yield '200 lines' => ['lines' => 200];
        yield '2 000 lines' => ['lines' => 2_000];
    }
}
