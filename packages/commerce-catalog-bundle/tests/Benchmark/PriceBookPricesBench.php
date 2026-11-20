<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\PriceBookPrices;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryPriceBooks;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The price books are asked for every product line on every cart change; the first call of a request also reads
 * every active book and indexes its tiers by SKU.
 *
 * @BeforeMethods("setUp")
 */
final class PriceBookPricesBench
{
    private readonly PriceBookPrices $prices;

    private Sellable $sellable;

    private readonly PricingContext $context;

    private readonly InMemoryPriceBooks $books;

    private readonly Currency $currency;

    public function __construct()
    {
        $this->currency = Currency::of('CAD');
        $this->books = new InMemoryPriceBooks();
        $this->prices = new PriceBookPrices($this->books);
        $this->sellable = Sellable::of(SellableReference::of(ProductSellables::TYPE, 'SKU-0'), 'Dice', Money::of(2_500, $this->currency), 'goods');
        $this->context = PricingContext::of(12, new DateTimeImmutable('2026-11-05 12:00:00'), 'wholesale', 'web');
    }

    /**
     * @param array{skus: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = $this->currency;

        foreach (['retail' => [null, ['store', 'wholesale']], 'wholesale' => ['wholesale', ['store']], 'store' => [null, []]] as $code => [$group, $after]) {
            $book = PriceBook::create($code, ucfirst($code), $currency, $group, 'store' === $code ? 'store' : null, after: $after);

            for ($i = 0; $i < $params['skus']; ++$i) {
                $book->setPrice(PriceTier::of('SKU-'.$i, 1, Money::of(2_000 + $i, $currency)));
                $book->setPrice(PriceTier::of('SKU-'.$i, 10, Money::of(1_800 + $i, $currency)));
            }

            $this->books->add($book);
        }

        $this->sellable = Sellable::of(SellableReference::of(ProductSellables::TYPE, 'SKU-'.intdiv($params['skus'], 2)), 'Dice', Money::of(2_500, $currency), 'goods');
        $this->prices->priceFor($this->sellable, $this->context);
    }

    /**
     * One line priced once the books are read: the wholesale book wins after the store book is skipped.
     *
     * @ParamProviders("catalogs")
     */
    public function benchPriceForWarm(): void
    {
        $this->prices->priceFor($this->sellable, $this->context);
    }

    /**
     * The first line of a request: every active book read and its tiers indexed, then the line priced.
     *
     * @ParamProviders("catalogs")
     *
     * @Revs(20)
     */
    public function benchPriceForCold(): void
    {
        $this->prices->reset();
        $this->prices->priceFor($this->sellable, $this->context);
    }

    /**
     * @return Generator<string, array{skus: int}>
     */
    public function catalogs(): Generator
    {
        yield '3 books x 10 SKUs' => ['skus' => 10];
        yield '3 books x 100 SKUs' => ['skus' => 100];
        yield '3 books x 500 SKUs' => ['skus' => 500];
    }
}
