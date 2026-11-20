<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Exception\NotSoldThere;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Option\OptionKind;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\CommunityCopies;
use Phil\CommerceCatalogBundle\Application\PriceRuleSellables;
use Phil\CommerceCatalogBundle\Application\ProductAvailability;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryCommunityCopies;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[CoversClass(PriceRuleSellables::class)]
#[CoversClass(CommunityCopies::class)]
#[CoversClass(ProductAvailability::class)]
final class PriceRulesTest extends TestCase
{
    private InMemoryProducts $products;

    private InMemoryCommunityCopies $ledger;

    private Product $zine;

    protected function setUp(): void
    {
        $this->zine = Product::create('ZINE', ProductKind::Digital, 'Zine', $this->cad(500), 'books');
        $this->zine->payWhatYouWant($this->cad(800), $this->cad(5_000));
        $this->zine->offerCommunityCopies($this->cad(500));

        $this->products = new InMemoryProducts($this->zine, Product::create('DICE', ProductKind::Physical, 'Dice', $this->cad(1_000), 'goods'));
        $this->ledger = new InMemoryCommunityCopies();
    }

    public function testTheBuyerChoosesThePriceThroughAnAmountOption(): void
    {
        $sellables = new PriceRuleSellables($this->products);
        $reference = PriceRuleSellables::reference($this->zine);
        $sellable = $sellables->resolve($reference);

        self::assertSame('pay_what_you_want:ZINE', $reference->value());
        self::assertTrue($sellables->supports('pay_what_you_want'));
        self::assertTrue($sellables->supports('community_copy'));
        self::assertFalse($sellables->supports('product'));
        self::assertNotNull($sellable);
        self::assertSame(0, $sellable->unitPrice->minorUnits);
        self::assertSame('CAD', $sellable->unitPrice->currency->code);
        self::assertSame('Zine', $sellable->description);
        self::assertSame('books', $sellable->taxCategory);
        self::assertSame($reference, $sellable->reference);
        self::assertEquals($sellables->optionsOf($reference, Currency::of('CAD')), $sellable->options);

        $option = $sellable->options->option('price');
        self::assertNotNull($option);
        self::assertCount(1, $sellable->options->options());
        self::assertSame(OptionKind::Amount, $option->kind);
        self::assertSame('Your price', $option->label);
        self::assertTrue($option->required);
        self::assertSame([500, 5_000], [$option->range?->minimum->minorUnits, $option->range?->maximum->minorUnits]);
        self::assertSame('CAD', $option->range?->maximum->currency->code);
        self::assertEquals(LineConfiguration::of(['price' => OptionValue::chosen('1200')]), PriceRuleSellables::configuration($this->cad(1_200)));
        self::assertTrue($sellables->optionsOf(SellableReference::of('community_copy', 'ZINE'), Currency::of('CAD'))->isEmpty());
    }

    public function testTheLabelOfTheChosenPriceIsTranslated(): void
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;

            /**
             * @param array<array-key, mixed> $parameters
             */
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id.' ('.$domain.')';
            }
        };

        $options = new PriceRuleSellables($this->products, null, $translator)->optionsOf(PriceRuleSellables::reference($this->zine), Currency::of('CAD'));

        self::assertSame('phil_commerce_catalog.chosen_price (phil_commerce_catalog)', $options->option('price')?->label);
    }

    public function testWithoutAMaximumOfItsOwnTheSiteDefaultBoundsTheChosenPrice(): void
    {
        $this->zine->payWhatYouWant($this->cad(800));
        $reference = PriceRuleSellables::reference($this->zine);

        self::assertNull(new PriceRuleSellables($this->products)->resolve($reference));
        self::assertSame(3_000, new PriceRuleSellables($this->products, null, null, 3_000)->resolve($reference)?->options->option('price')?->range?->maximum->minorUnits);
        self::assertSame(800, new PriceRuleSellables($this->products, null, null, 800)->resolve($reference)?->options->option('price')?->range?->maximum->minorUnits);
        self::assertNull(new PriceRuleSellables($this->products, null, null, 799)->resolve($reference));

        $this->zine->payWhatYouWant(null, $this->cad(2_000));
        self::assertSame(2_000, new PriceRuleSellables($this->products, null, null, 3_000)->resolve($reference)?->options->option('price')?->range?->maximum->minorUnits);
    }

    public function testAProductTheBuyerDoesNotChooseThePriceOfSellsNothing(): void
    {
        $sellables = new PriceRuleSellables($this->products, null, null, 10_000);
        $family = Product::create('SHIRT', ProductKind::Physical, 'Shirt', $this->cad(500), 'goods');
        $family->payWhatYouWant();
        $family->defineAxes(['size']);

        $gone = Product::create('GONE', ProductKind::Digital, 'Gone', $this->cad(500), 'books');
        $gone->payWhatYouWant();
        $gone->withdraw();

        $this->products->add($family);
        $this->products->add($gone);

        foreach (['ZINE@1200', 'DICE', 'MEEPLE', 'SHIRT', 'GONE'] as $id) {
            self::assertNull($sellables->resolve(SellableReference::of('pay_what_you_want', $id)), $id);
        }

        self::assertNull(PriceRuleSellables::skuOf(SellableReference::of('product', 'ZINE')));
        self::assertSame('ZINE', PriceRuleSellables::skuOf(SellableReference::of('pay_what_you_want', 'ZINE')));
        self::assertSame('ZINE', PriceRuleSellables::skuOf(SellableReference::of('community_copy', 'ZINE')));
    }

    public function testALineWhoseBuyerNoLongerChoosesThePriceIsNotLeftAtZero(): void
    {
        $sellables = new PriceRuleSellables($this->products);
        $this->zine->fixPrice();

        try {
            $sellables->optionsOf(PriceRuleSellables::reference($this->zine), Currency::of('CAD'));
            self::fail('A product sold at its price declares no chosen price.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The buyer no longer chooses the price of ZINE: its line is removed, or the product sold at its price.', $catalogRefused->getMessage());
        }

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('The buyer no longer chooses the price of MEEPLE');

        $sellables->optionsOf(SellableReference::of('pay_what_you_want', 'MEEPLE'), Currency::of('CAD'));
    }

    public function testAChosenPriceCountsInTheCurrencyOfItsProduct(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('The product ZINE counts in CAD; EUR is refused.');

        new PriceRuleSellables($this->products)->optionsOf(PriceRuleSellables::reference($this->zine), Currency::of('EUR'));
    }

    public function testACommunityCopyIsSoldAtItsCopyPrice(): void
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;
        };
        $copy = new PriceRuleSellables($this->products, null, $translator)->resolve(SellableReference::of('community_copy', 'ZINE'));

        self::assertNotNull($copy);
        self::assertSame(500, $copy->unitPrice->minorUnits);
        self::assertSame('phil_commerce_catalog.community_copy', $copy->description);
        self::assertSame('Community copy of Zine', new PriceRuleSellables($this->products)->resolve(SellableReference::of('community_copy', 'ZINE'))?->description);
        self::assertNull(new PriceRuleSellables($this->products)->resolve(SellableReference::of('community_copy', 'DICE')));
        self::assertNull(new PriceRuleSellables($this->products)->resolve(SellableReference::of('community_copy', 'MEEPLE')));
    }

    public function testACommunityCopyIsDescribedWithTheNameOfItsProduct(): void
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait;

            /**
             * @param array<array-key, mixed> $parameters
             */
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtr('Copy of %name% ('.$domain.')', $parameters);
            }
        };

        self::assertSame('Copy of Zine (phil_commerce_catalog)', new PriceRuleSellables($this->products, null, $translator)->resolve(SellableReference::of('community_copy', 'ZINE'))?->description);
    }

    public function testTheChannelsThatSellAProductSellItsPriceRules(): void
    {
        $this->zine->sellThrough(['web']);
        $availability = new ProductAvailability($this->products);

        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('pay_what_you_want', 'ZINE'), 'web'));
        self::assertEquals(Offer::hidden(), $availability->offerThrough(SellableReference::of('pay_what_you_want', 'ZINE'), 'store'));
        self::assertEquals(Offer::hidden(), $availability->offerThrough(SellableReference::of('community_copy', 'ZINE'), 'store'));
        self::assertEquals(Offer::hidden(), $availability->offerThrough(SellableReference::of('product', 'ZINE'), 'store'));
        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('pay_what_you_want', 'MEEPLE'), 'store'));
        self::assertEquals(Offer::full(), $availability->offerThrough(SellableReference::of('offer', 'ZINE'), 'store'));

        $this->zine->changeOffer(Offer::of(Visibility::Listed, false));
        self::assertEquals(Offer::of(Visibility::Listed, false), $availability->offerThrough(SellableReference::of('community_copy', 'ZINE'), 'web'));
    }

    /**
     * What is paid above the suggested price, and the community copies bought, fund copies once per order.
     */
    public function testAPaidOrderFundsCommunityCopiesOnce(): void
    {
        $order = $this->order('1', [['pay_what_you_want', 'ZINE', 2, 1_200], ['pay_what_you_want', 'ZINE', 1, 700], ['community_copy', 'ZINE', 3], ['product', 'DICE', 1]]);
        $copies = new CommunityCopies($this->products, $this->ledger);
        $at = new DateTimeImmutable('2026-11-12 10:00');

        self::assertSame(['ZINE'], $copies->fund($order, $at));
        self::assertSame([], $copies->fund($order, $at));
        self::assertSame(2_300, $this->ledger->funded('ZINE'));
        $funding = $this->ledger->fundings["ZINE\0".'1'];
        self::assertSame($at, $funding->fundedAt);
        self::assertSame('1', $funding->orderNumber);
        self::assertSame(2_300, $copies->funded($this->zine)?->minorUnits);
        self::assertSame(4, $copies->available($this->zine));
    }

    public function testALineThatFundsNothingDoesNotStopTheNextOnes(): void
    {
        $copies = new CommunityCopies($this->products, $this->ledger);

        self::assertSame(['ZINE'], $copies->fund($this->order('1', [['product', 'DICE', 1], ['pay_what_you_want', 'ZINE', 1, 1_200]]), new DateTimeImmutable()));
        self::assertSame(400, $this->ledger->funded('ZINE'));
    }

    public function testASkuOfDigitsIsFundedByItsName(): void
    {
        $book = Product::create('1234', ProductKind::Digital, 'Book', $this->cad(1_000), 'books');
        $book->offerCommunityCopies($this->cad(1_000));

        $this->products->add($book);

        self::assertSame(['1234', 'ZINE'], new CommunityCopies($this->products, $this->ledger)->fund($this->order('1', [['community_copy', '1234', 2], ['community_copy', 'ZINE', 1], ['community_copy', '1234', 1]]), new DateTimeImmutable()));
        self::assertSame(3_000, $this->ledger->funded('1234'));
    }

    public function testWithoutASuggestedPriceWhatIsPaidAboveTheBasePriceFunds(): void
    {
        $this->zine->payWhatYouWant(null, $this->cad(5_000));
        $copies = new CommunityCopies($this->products, $this->ledger);
        $copies->fund($this->order('1', [['pay_what_you_want', 'ZINE', 2, 700]]), new DateTimeImmutable());

        self::assertSame(400, $this->ledger->funded('ZINE'));
        self::assertSame(0, $copies->available($this->zine));
    }

    public function testAnOrderGivingNothingOrInAnotherCurrencyFundsNothing(): void
    {
        $copies = new CommunityCopies($this->products, $this->ledger);
        self::assertSame([], $copies->fund($this->order('1', [['pay_what_you_want', 'ZINE', 1, 800], ['product', 'DICE', 1]]), new DateTimeImmutable()));

        $this->zine->offerCommunityCopies(null);
        self::assertSame([], $copies->fund($this->order('2', [['pay_what_you_want', 'ZINE', 1, 900]]), new DateTimeImmutable()));
        self::assertNull($copies->funded($this->zine));
        self::assertSame(0, $copies->available($this->zine));

        self::assertSame([], $copies->fund(Order::open('3', Currency::of('EUR')), new DateTimeImmutable()));
        self::assertSame([], $this->ledger->fundings);
    }

    public function testTheCoreSaysWhichOrderWasPaid(): void
    {
        $order = $this->order('7', [['community_copy', 'ZINE', 1]]);
        $orders = new readonly class($order) implements OrderRepository {
            public function __construct(private Order $order)
            {
            }

            #[Override]
            public function find(string $number): ?Order
            {
                return $this->order->number === $number ? $this->order : null;
            }

            #[Override]
            public function add(Order $order): void
            {
            }
        };
        $copies = new CommunityCopies($this->products, $this->ledger, $orders);
        $at = new DateTimeImmutable('2026-11-12 10:00');

        self::assertSame([], $copies(new OrderPurchased('8', null, null, $this->cad(500), $at)));
        self::assertSame([], new CommunityCopies($this->products, $this->ledger)(new OrderPurchased('7', null, null, $this->cad(500), $at)));
        self::assertSame(['ZINE'], $copies(new OrderPurchased('7', null, null, $this->cad(500), $at)));
        self::assertSame($at, $this->ledger->fundings["ZINE\0".'7']->fundedAt);
    }

    public function testACopyIsClaimedOncePerPersonWhileCopiesAreLeft(): void
    {
        $copies = new CommunityCopies($this->products, $this->ledger);
        $this->ledger->addFunding(CommunityCopyFunding::of('ZINE', '1', 1_000, new DateTimeImmutable()));
        $at = new DateTimeImmutable('2026-11-12 10:00');

        $claim = $copies->claim($this->zine, 'member-1', $at);
        self::assertSame(['ZINE', 'member-1', $at], [$claim->sku, $claim->claimant, $claim->claimedAt]);
        self::assertSame(['ZINE'], $this->ledger->locks);
        self::assertSame(1, $copies->available($this->zine));

        try {
            $copies->claim($this->zine, 'member-1', $at);
            self::fail('A second claim by the same person is refused.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('A community copy of ZINE was already claimed by the same person.', $catalogRefused->getMessage());
        }

        $copies->claim($this->zine, 'member-2', $at);
        self::assertSame(0, $copies->available($this->zine));

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('No community copy of ZINE is left to claim.');

        $copies->claim($this->zine, 'member-3', $at);
    }

    public function testMoreClaimsThanFundsLeaveNoneAvailable(): void
    {
        $copies = new CommunityCopies($this->products, $this->ledger);
        $this->ledger->addFunding(CommunityCopyFunding::of('ZINE', '1', 500, new DateTimeImmutable()));
        $copies->claim($this->zine, 'member-1', new DateTimeImmutable());
        $this->zine->offerCommunityCopies($this->cad(1_000));

        self::assertSame(0, $copies->available($this->zine));
    }

    public function testAProductWithoutCommunityCopiesHasNoneToClaim(): void
    {
        $this->zine->offerCommunityCopies(null);

        try {
            new CommunityCopies($this->products, $this->ledger)->claim($this->zine, 'member-1', new DateTimeImmutable());
            self::fail('No copy is claimed of a product that offers none.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The product ZINE offers no community copies.', $catalogRefused->getMessage());
            self::assertSame([], $this->ledger->locks);
        }
    }

    public function testAClaimantIsAStableIdentifier(): void
    {
        $this->expectException(InvalidValue::class);

        new CommunityCopies($this->products, $this->ledger)->claim($this->zine, 'Jane Doe', new DateTimeImmutable());
    }

    public function testAFundingGivesMoreThanZero(): void
    {
        $this->expectException(InvalidValue::class);

        CommunityCopyFunding::of('ZINE', '1', 0, new DateTimeImmutable());
    }

    /**
     * Through the core: a chosen price is an option of the line, charged as its supplement, refused out of bounds or
     * where the product is not sold; another price is another line.
     */
    public function testTheCoreSellsAChosenPrice(): void
    {
        $modifier = $this->modifier([new ProductAvailability($this->products)]);
        $order = Order::open('1', Currency::of('CAD'));
        $reference = PriceRuleSellables::reference($this->zine);

        $line = $modifier->add($order, $reference, 1, PricingContext::of(1, new DateTimeImmutable()), PriceRuleSellables::configuration($this->cad(900)));
        self::assertSame(900, $order->total()->minorUnits);
        self::assertSame(0, $line->unitPrice->minorUnits);
        self::assertSame('9.00 CAD', $line->configuration->value('price')?->display);
        self::assertSame(900, PriceRuleSellables::chosenPriceOf($line));

        $modifier->add($order, $reference, 2, PricingContext::of(2, new DateTimeImmutable()), PriceRuleSellables::configuration($this->cad(5_000)));
        self::assertCount(2, $order->lines());
        self::assertSame(10_900, $order->total()->minorUnits);

        foreach ([100, 5_001] as $price) {
            try {
                $modifier->add($order, $reference, 1, PricingContext::of(1, new DateTimeImmutable()), PriceRuleSellables::configuration($this->cad($price)));
                self::fail('A price out of bounds is refused.');
            } catch (InvalidConfiguration $invalidConfiguration) {
                self::assertSame('price', $invalidConfiguration->option);
            }
        }

        try {
            $modifier->add($order, $reference, 1, PricingContext::of(1, new DateTimeImmutable()));
            self::fail('A chosen price is required.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertSame('price', $invalidConfiguration->option);
            self::assertSame(10_900, $order->total()->minorUnits);
        }

        $this->zine->sellThrough(['web']);
        $order->changeChannel('store');

        $this->expectException(NotSoldThere::class);

        $modifier->add($order, $reference, 1, PricingContext::of(1, new DateTimeImmutable()), PriceRuleSellables::configuration($this->cad(900)));
    }

    public function testOnlyALineOfAChosenPriceHasOne(): void
    {
        $order = $this->order('1', [['product', 'DICE', 1], ['community_copy', 'ZINE', 1]]);

        foreach ($order->lines() as $line) {
            self::assertNull(PriceRuleSellables::chosenPriceOf($line));
        }

        $configuration = PriceRuleSellables::configuration($this->cad(900));
        $poster = OrderLine::stored(Sellable::of(SellableReference::of('product', 'POSTER'), 'Poster', $this->cad(100), 'goods'), 1, $this->cad(100), $configuration);
        $chosen = OrderLine::stored(Sellable::of(PriceRuleSellables::reference($this->zine), 'Zine', $this->cad(100), 'books'), 1, $this->cad(100), $configuration);

        self::assertNull(PriceRuleSellables::chosenPriceOf($poster), 'an option of the same code on another sellable');
        self::assertSame(1_000, PriceRuleSellables::chosenPriceOf($chosen), 'the unit price and the price chosen');
    }

    public function testALineOfAChosenPriceWithoutItsOptionGivesNothing(): void
    {
        $this->zine->payWhatYouWant(null, $this->cad(5_000));
        $book = Product::create('FREE', ProductKind::Digital, 'Free', $this->cad(0), 'books');
        $book->payWhatYouWant(null, $this->cad(5_000));
        $book->offerCommunityCopies($this->cad(500));

        $this->products->add($book);

        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(PriceRuleSellables::reference($book), 'Free', $this->cad(0), 'books'), 1, $this->cad(0));

        $line = $order->lines()[0];

        self::assertNull(PriceRuleSellables::chosenPriceOf($line));
        self::assertSame([], new CommunityCopies($this->products, $this->ledger)->fund($order, new DateTimeImmutable()));
        self::assertSame([], $this->ledger->fundings);
    }

    /**
     * @param list<array{0: string, 1: string, 2: int, 3?: int}> $lines the type, identifier, quantity and chosen price
     */
    private function order(string $number, array $lines): Order
    {
        $modifier = $this->modifier();
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as $line) {
            $configuration = isset($line[3]) ? PriceRuleSellables::configuration($this->cad($line[3])) : null;
            $modifier->add($order, SellableReference::of($line[0], $line[1]), $line[2], PricingContext::of($line[2], new DateTimeImmutable()), $configuration);
        }

        return $order;
    }

    /**
     * @param list<SellableAvailability> $availabilities
     */
    private function modifier(array $availabilities = []): OrderModifier
    {
        $sellables = new PriceRuleSellables($this->products);
        $options = new LineOptions([$sellables]);

        return new OrderModifier([new ProductSellables($this->products), $sellables], [], [new OptionSupplementProcessor($options)], $availabilities, $options);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
