<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\CommunityCopies;
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Application\Contract\CommunityCopyLedger;
use Phil\CommerceCatalogBundle\Application\PriceRuleSellables;
use Phil\CommerceCatalogBundle\Application\ProductTexts;
use Phil\CommerceCatalogBundle\Application\ProductVariants;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\Repository\PriceBookRepository;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrineCommunityCopyLedger;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrinePriceBookRepository;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrineProductRepository;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\ProductFeeds;
use Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle;
use Phil\CommerceCatalogBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Products, their variants, composites and translations, and price books stored, read back, and plugged into the core
 * by autoconfiguration: an order built through the container takes the book's price for the buyer's group, and the
 * core taxes it.
 */
#[CoversClass(PhilCommerceCatalogBundle::class)]
#[CoversClass(DoctrineProductRepository::class)]
#[CoversClass(DoctrinePriceBookRepository::class)]
#[CoversClass(DoctrineCommunityCopyLedger::class)]
final class CatalogFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $dice = Product::create('DICE', ProductKind::Physical, 'Dice set', Money::of(2_000, Currency::of('CAD')), 'goods');
        $dice->describe('colour', 'red');
        $this->service(ProductRepository::class, ProductRepository::class)->add($dice);

        $books = $this->service(PriceBookRepository::class, PriceBookRepository::class);
        $wholesale = PriceBook::create('wholesale', 'Wholesale', Currency::of('CAD'), 'wholesale');
        $wholesale->setPrice(PriceTier::of('DICE', 1, Money::of(1_500, Currency::of('CAD'))));

        $books->add($wholesale);
        $retired = PriceBook::create('retired', 'Retired', Currency::of('CAD'));
        $retired->setPrice(PriceTier::of('DICE', 1, Money::of(1, Currency::of('CAD'))));
        $retired->withdraw();

        $books->add($retired);

        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * The feed declared in the configuration, published from the stored catalogue: the product with its French name,
     * its colour, its wholesale price left out — a book for one customer group is no sale —, and the product only shown
     * out of stock; the hidden one left out.
     */
    public function testTheFeedIsPublishedFromTheCatalogue(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $products = $this->service(ProductRepository::class, ProductRepository::class);
        $this->service(ProductTexts::class, ProductTexts::class)->translate($products->find('DICE') ?? self::fail('No dice.'), 'fr')->rename('Jeu de dés');
        $soon = Product::create('SOON', ProductKind::Physical, 'Coming soon', Money::of(3_000, Currency::of('CAD')), 'goods');
        $soon->changeOffer(Offer::of(Visibility::Listed, false));

        $secret = Product::create('SECRET', ProductKind::Physical, 'Secret', Money::of(3_000, Currency::of('CAD')), 'goods');
        $secret->changeOffer(Offer::hidden());

        $products->add($soon);
        $products->add($secret);

        $entityManager->flush();

        $feed = $this->service(ProductFeeds::class, ProductFeeds::class)->publish('google');
        $xml = (string) file_get_contents($feed->path);

        self::assertSame(2, $feed->items);
        self::assertStringContainsString('<title>The Dice Shop</title>', $xml);
        self::assertStringContainsString("<g:id>DICE</g:id>\n<g:title>Jeu de dés</g:title>\n<g:link>http://shop.localhost/products/DICE</g:link>\n<g:image_link>http://shop.localhost/images/DICE.jpg</g:image_link>\n<g:availability>in_stock</g:availability>\n<g:price>20.00 CAD</g:price>", $xml);
        self::assertStringContainsString('<g:color>red</g:color>', $xml);
        self::assertStringContainsString("<g:id>SOON</g:id>\n<g:title>Coming soon</g:title>", $xml);
        self::assertStringContainsString('<g:availability>out_of_stock</g:availability>', $xml);
        self::assertStringNotContainsString('SECRET', $xml);
        self::assertStringNotContainsString('<g:sale_price>', $xml);
        self::assertTrue(self::getContainer()->has('console.command_loader'));
    }

    /**
     * The active price books only, by code; their `after` lists are read back as they were stored.
     */
    public function testTheActiveBooksComeByCode(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $books = $this->service(PriceBookRepository::class, PriceBookRepository::class);
        $books->add(PriceBook::create('z-top', 'Top', Currency::of('CAD')));
        $books->add(PriceBook::create('a-low', 'Low', Currency::of('CAD'), after: ['z-top', 'wholesale']));

        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['a-low', 'wholesale', 'z-top'], array_map(static fn (PriceBook $book): string => $book->code, $books->active()));
        self::assertSame(['z-top', 'wholesale'], $books->find('a-low')?->after());
    }

    /**
     * A family's variants and a composite product, stored and read back; a variant is sold through the core, the family
     * is not, and the composite is one line taxed whole.
     */
    public function testVariantsAndCompositesAreStoredAndSold(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $products = $this->service(ProductRepository::class, ProductRepository::class);
        $variants = $this->service(ProductVariants::class, ProductVariants::class);
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $shirt->defineAxes(['size', 'colour']);

        $products->add($shirt);
        $variants->create($shirt, 'SHIRT-M-RED', ['colour' => 'red', 'size' => 'M']);
        $variants->create($shirt, 'SHIRT-L-RED', ['size' => 'L', 'colour' => 'red'], Money::of(2_700, Currency::of('CAD')));

        $box = Product::create('BOX', ProductKind::Physical, 'Starter box', Money::of(5_000, Currency::of('CAD')), 'goods');
        $products->add($box);
        $entityManager->flush();
        $this->service(CompositeProducts::class, CompositeProducts::class)->compose($box, [ProductComponent::of('DICE', 2), ProductComponent::of('SHIRT-M-RED')]);
        $entityManager->flush();
        $entityManager->clear();

        $shirt = $products->find('SHIRT');
        self::assertNotNull($shirt);
        self::assertSame(['size', 'colour'], $shirt->axes());
        self::assertSame(['SHIRT-L-RED', 'SHIRT-M-RED'], array_map(static fn (Product $variant): string => $variant->sku, $variants->variantsOf($shirt)));
        self::assertSame(['size' => ['L', 'M'], 'colour' => ['red']], $variants->choices($shirt));
        self::assertSame('SHIRT-M-RED', $variants->find($shirt, ['colour' => 'red', 'size' => 'M'])?->sku);
        self::assertSame(['size' => 'M', 'colour' => 'red'], $products->find('SHIRT-M-RED')?->options());
        self::assertSame([['DICE', 2], ['SHIRT-M-RED', 1]], array_map(static fn (ProductComponent $part): array => [$part->sku, $part->quantity], $this->service(CompositeProducts::class, CompositeProducts::class)->partsOf(SellableReference::of('product', 'BOX'))));

        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('V', Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'SHIRT-L-RED'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->add($order, SellableReference::of('product', 'BOX'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->changeTaxZone($order, 'QC');
        self::assertSame(['T-shirt — L / red', 'Starter box'], array_map(static fn (OrderLine $line): string => $line->description, $order->lines()));
        self::assertSame(7_700 + 385 + 768, $order->total()->minorUnits);

        $this->expectException(UnknownSellable::class);
        $modifier->add($order, SellableReference::of('product', 'SHIRT'), 1, PricingContext::of(1, new DateTimeImmutable()));
    }

    /**
     * A product's name in the buyer's locale describes its line.
     */
    public function testANameIsTranslated(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $texts = $this->service(ProductTexts::class, ProductTexts::class);
        $dice = $this->service(ProductRepository::class, ProductRepository::class)->find('DICE');
        self::assertNotNull($dice);
        $texts->translate($dice, 'fr')->rename('Jeu de dés');
        $entityManager->flush();
        $entityManager->clear();

        $dice = $this->service(ProductRepository::class, ProductRepository::class)->find('DICE');
        self::assertNotNull($dice);
        self::assertSame('Jeu de dés', $texts->of([$dice], 'fr')->name($dice));
        self::assertSame('Dice set', $texts->of([$dice], 'en')->name($dice));
        self::assertSame(['fr'], $texts->locales($dice));

        $order = Order::open('T', Currency::of('CAD'));
        $this->service(OrderModifier::class, OrderModifier::class)->add($order, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable()));
        self::assertSame('Dice set', $order->lines()[0]->description);
    }

    public function testTheCatalogIsReadBackAndPricesOrders(): void
    {
        $product = $this->service(ProductRepository::class, ProductRepository::class)->find('DICE');
        self::assertNotNull($product);
        self::assertSame(['colour' => 'red'], $product->attributes());
        self::assertSame(ProductKind::Physical, $product->kind);

        $books = $this->service(PriceBookRepository::class, PriceBookRepository::class);
        self::assertSame(['wholesale'], array_map(static fn (PriceBook $book): string => $book->code, $books->active()));
        self::assertSame(1_500, $books->find('wholesale')?->priceFor('DICE', 1)?->minorUnits);
        self::assertFalse($books->find('retired')?->isActive());

        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $retail = Order::open('R', Currency::of('CAD'));
        $modifier->add($retail, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $wholesale = Order::open('W', Currency::of('CAD'));
        $modifier->add($wholesale, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable(), 'wholesale'));
        $modifier->changeTaxZone($wholesale, 'QC');

        self::assertSame(2_000, $retail->total()->minorUnits);
        self::assertSame(1_500 + 75 + 150, $wholesale->total()->minorUnits);
    }

    /**
     * A paid order funds community copies through the core's event; claims are counted in SQL, inside a transaction
     * that locks the product.
     */
    public function testCommunityCopiesAreFundedAndClaimed(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $zine = Product::create('ZINE', ProductKind::Digital, 'Zine', Money::of(500, Currency::of('CAD')), 'books');
        $zine->payWhatYouWant(Money::of(800, Currency::of('CAD')));
        $zine->offerCommunityCopies(Money::of(500, Currency::of('CAD')));
        $this->service(ProductRepository::class, ProductRepository::class)->add($zine);
        $entityManager->flush();

        $order = Order::open('C-1', Currency::of('CAD'));
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $modifier->add($order, PriceRuleSellables::reference($zine), 1, PricingContext::of(1, new DateTimeImmutable()), PriceRuleSellables::configuration(Money::of(1_300, Currency::of('CAD'))));
        self::assertSame(1_300, $order->total()->minorUnits);
        $modifier->add($order, SellableReference::of('community_copy', 'ZINE'), 1, PricingContext::of(1, new DateTimeImmutable()));
        self::assertSame(['Zine', 'Community copy of Zine'], array_map(static fn (OrderLine $line): string => $line->description, $order->lines()));
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $entityManager->flush();

        $dispatcher = $this->service('event_dispatcher', EventDispatcherInterface::class);
        $dispatcher->dispatch(new OrderPurchased('C-1', null, null, $order->total(), new DateTimeImmutable()));
        $dispatcher->dispatch(new OrderPurchased('C-1', null, null, $order->total(), new DateTimeImmutable()));

        $entityManager->flush();
        $entityManager->clear();

        $copies = $this->service(CommunityCopies::class, CommunityCopies::class);
        $ledger = $this->service(CommunityCopyLedger::class, CommunityCopyLedger::class);
        $zine = $this->service(ProductRepository::class, ProductRepository::class)->find('ZINE');
        self::assertNotNull($zine);
        self::assertSame(1_000, $ledger->funded('ZINE'));
        self::assertSame(0, $ledger->funded('DICE'));
        self::assertTrue($ledger->isFundedBy('ZINE', 'C-1'));
        self::assertFalse($ledger->isFundedBy('ZINE', 'C-2'));
        self::assertSame(2, $copies->available($zine));

        $entityManager->wrapInTransaction(static fn (): CommunityCopyClaim => $copies->claim($zine, 'member-1', new DateTimeImmutable()));
        $entityManager->clear();

        self::assertSame(1, $ledger->claimed('ZINE'));
        self::assertSame(0, $ledger->claimed('DICE'));
        self::assertTrue($ledger->hasClaimed('ZINE', 'member-1'));
        self::assertFalse($ledger->hasClaimed('ZINE', 'member-2'));
        self::assertSame(1, $copies->available($zine));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
