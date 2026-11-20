<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\SellableOffers;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Exception\NotSoldThere;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\PriceBookPrices;
use Phil\CommerceCatalogBundle\Application\ProductAvailability;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryPriceBooks;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductSellables::class)]
#[CoversClass(PriceBookPrices::class)]
#[CoversClass(ProductAvailability::class)]
final class CatalogPricingTest extends TestCase
{
    private InMemoryProducts $products;

    protected function setUp(): void
    {
        $this->products = new InMemoryProducts(
            Product::create('DICE', ProductKind::Physical, 'Dice', $this->cad(1_000), 'goods'),
            Product::create('PDF', ProductKind::Digital, 'Rulebook', $this->cad(2_000), 'digital'),
        );
    }

    public function testAProductIsSoldAtItsBasePrice(): void
    {
        $sellables = new ProductSellables($this->products);

        self::assertTrue($sellables->supports('product'));
        self::assertFalse($sellables->supports('offer'));

        $sellable = $sellables->resolve(SellableReference::of('product', 'DICE'));
        self::assertNotNull($sellable);
        self::assertSame('Dice', $sellable->description);
        self::assertSame(1_000, $sellable->unitPrice->minorUnits);
        self::assertSame('goods', $sellable->taxCategory);
        self::assertNull($sellables->resolve(SellableReference::of('product', 'MEEPLE')));
    }

    public function testAWithdrawnProductIsNotSold(): void
    {
        $this->products->find('DICE')?->withdraw();

        self::assertNull(new ProductSellables($this->products)->resolve(SellableReference::of('product', 'DICE')));
    }

    /**
     * A family is chosen through its variants: only they are sold.
     */
    public function testAFamilyIsNotSoldItsVariantsAre(): void
    {
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', $this->cad(2_500), 'goods');
        $shirt->defineAxes(['size']);

        $this->products->add($shirt);
        $this->products->add(Product::variant($shirt, 'SHIRT-M', ['size' => 'M']));

        $sellables = new ProductSellables($this->products);

        self::assertNull($sellables->resolve(SellableReference::of('product', 'SHIRT')));
        self::assertSame('T-shirt — M', $sellables->resolve(SellableReference::of('product', 'SHIRT-M'))?->description);
    }

    /**
     * A product sold through some channels only is refused to an order of another, through the core.
     */
    public function testAChannelSellsItsSelectionOfProducts(): void
    {
        $this->products->find('DICE')?->sellThrough(['second-shop']);
        $availability = new ProductAvailability($this->products);

        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('product', 'DICE'), 'second-shop'));
        self::assertEquals(Offer::hidden(), $availability->offerThrough(SellableReference::of('product', 'DICE'), 'web'));
        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('product', 'PDF'), 'web'));
        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('product', 'MEEPLE'), 'web'));
        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('offer', 'DICE'), 'web'));

        $modifier = new OrderModifier([new ProductSellables($this->products)], [], [], [$availability]);
        $order = Order::open('1', Currency::of('CAD'));
        $order->changeChannel('web');

        $this->expectException(NotSoldThere::class);

        $modifier->add($order, SellableReference::of('product', 'DICE'), 1, $this->context(1));
    }

    /**
     * A product no order may hold — announced, sold out for good — keeps its page, but the cart refuses it, through the core.
     */
    public function testAProductNoOrderMayHoldIsRefusedToTheCart(): void
    {
        $this->products->find('PDF')?->changeOffer(Offer::of(Visibility::Listed, false));
        $availability = new ProductAvailability($this->products);
        $offers = new SellableOffers([$availability]);
        $modifier = new OrderModifier([new ProductSellables($this->products)], [], [], [$availability]);
        $order = Order::open('1', Currency::of('CAD'));
        $order->changeChannel('web');

        self::assertEquals(Offer::of(Visibility::Listed, false), $offers->of(SellableReference::of('product', 'PDF'), 'web'));

        $modifier->add($order, SellableReference::of('product', 'DICE'), 1, $this->context(1));
        self::assertCount(1, $order->lines());

        try {
            $modifier->add($order, SellableReference::of('product', 'PDF'), 1, $this->context(1));
            self::fail('The cart took a product only shown.');
        } catch (NotSoldThere $notSoldThere) {
            self::assertSame('product:PDF is not sold through web.', $notSoldThere->getMessage());
        }

        self::assertCount(1, $order->lines());
    }

    /**
     * The first book in the order of the `after` lists that applies and prices the SKU for the quantity wins.
     */
    public function testTheFirstBookThatAppliesAndPricesWins(): void
    {
        $prices = new PriceBookPrices(new InMemoryPriceBooks(
            $this->book('everyone', ['vip', 'wholesale'], null, ['DICE' => [1 => 950]]),
            $this->book('wholesale', ['vip'], 'wholesale', ['DICE' => [10 => 700]]),
            $this->book('vip', [], 'vip', ['DICE' => [1 => 500]]),
            $this->book('euro', [], null, ['DICE' => [1 => 1]], 'EUR'),
        ));
        $dice = $this->sellable('DICE', 1_000);

        self::assertSame(950, $prices->priceFor($dice, $this->context(1))?->minorUnits);
        self::assertSame(950, $prices->priceFor($dice, $this->context(9, 'wholesale'))?->minorUnits);
        self::assertSame(700, $prices->priceFor($dice, $this->context(10, 'wholesale'))?->minorUnits);
        self::assertSame(500, $prices->priceFor($dice, $this->context(1, 'vip'))?->minorUnits);
        self::assertNull($prices->priceFor($this->sellable('PDF', 2_000), $this->context(1)));
    }

    /**
     * The books are read once for every line priced, until they are reset.
     */
    public function testTheBooksAreReadOnceUntilReset(): void
    {
        $books = new InMemoryPriceBooks($this->book('everyone', ['vip'], null, ['DICE' => [1 => 950, 10 => 800, 5 => 900], 'D6' => [1 => 100]]));
        $prices = new PriceBookPrices($books);
        $dice = $this->sellable('DICE', 1_000);

        self::assertSame(950, $prices->priceFor($dice, $this->context(1))?->minorUnits);
        self::assertSame(900, $prices->priceFor($dice, $this->context(7))?->minorUnits);
        self::assertSame(800, $prices->priceFor($dice, $this->context(12))?->minorUnits);
        self::assertSame(100, $prices->priceFor($this->sellable('D6', 200), $this->context(1))?->minorUnits);
        self::assertSame(1, $books->reads);

        $books->add($this->book('vip', [], null, ['DICE' => [1 => 500]]));
        self::assertSame(950, $prices->priceFor($dice, $this->context(1))?->minorUnits);

        $prices->reset();
        self::assertSame(500, $prices->priceFor($dice, $this->context(1))?->minorUnits);
        self::assertSame(2, $books->reads);
    }

    public function testOnlyProductsArePricedByTheBooks(): void
    {
        $prices = new PriceBookPrices(new InMemoryPriceBooks($this->book('everyone', [], null, ['gold' => [1 => 1]])));
        $offer = Sellable::of(SellableReference::of('offer', 'gold'), 'Gold', $this->cad(1_000), 'digital');

        self::assertNull($prices->priceFor($offer, $this->context(1)));
    }

    public function testAWithdrawnOrNotYetStartedBookIsSkipped(): void
    {
        $withdrawn = $this->book('withdrawn', [], null, ['DICE' => [1 => 100]]);
        $withdrawn->withdraw();

        $later = PriceBook::create('later', 'Later', Currency::of('CAD'), startsAt: new DateTimeImmutable('2030-01-01'));
        $later->setPrice(PriceTier::of('DICE', 1, $this->cad(200)));

        $prices = new PriceBookPrices(new InMemoryPriceBooks($withdrawn, $later, $this->book('everyone', ['later', 'withdrawn'], null, ['DICE' => [1 => 900]])));

        self::assertSame(900, $prices->priceFor($this->sellable('DICE', 1_000), $this->context(1))?->minorUnits);
    }

    /**
     * Through the core: the line takes the book's price for its whole quantity, and the base price otherwise.
     */
    public function testTheCorePricesALineFromTheBooks(): void
    {
        $modifier = new OrderModifier(
            [new ProductSellables($this->products)],
            [new PriceBookPrices(new InMemoryPriceBooks($this->book('bulk', [], null, ['DICE' => [3 => 800]])))],
            [],
        );
        $order = Order::open('1', Currency::of('CAD'));

        $modifier->add($order, SellableReference::of('product', 'DICE'), 2, $this->context(1));
        self::assertSame(1_000, $order->lines()[0]->unitPrice->minorUnits);

        $modifier->add($order, SellableReference::of('product', 'DICE'), 1, $this->context(1));
        self::assertSame(800, $order->lines()[0]->unitPrice->minorUnits);
        self::assertSame(2_400, $order->total()->minorUnits);

        $this->expectException(UnknownSellable::class);

        $modifier->add($order, SellableReference::of('product', 'MEEPLE'), 1, $this->context(1));
    }

    /**
     * @param list<string>                   $after  the books tried before it
     * @param array<string, array<int, int>> $prices SKU → minimum quantity → unit price
     */
    private function book(string $code, array $after, ?string $group, array $prices, string $currency = 'CAD'): PriceBook
    {
        $book = PriceBook::create($code, ucfirst($code), Currency::of($currency), $group, after: $after);

        foreach ($prices as $sku => $tiers) {
            foreach ($tiers as $minQuantity => $price) {
                $book->setPrice(PriceTier::of($sku, $minQuantity, Money::of($price, Currency::of($currency))));
            }
        }

        return $book;
    }

    private function sellable(string $sku, int $price): Sellable
    {
        return Sellable::of(SellableReference::of('product', $sku), $sku, $this->cad($price), 'goods');
    }

    private function context(int $quantity, ?string $group = null): PricingContext
    {
        return PricingContext::of($quantity, new DateTimeImmutable('2026-11-03T12:00:00Z'), $group);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
