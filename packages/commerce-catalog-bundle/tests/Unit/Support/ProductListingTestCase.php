<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use JsonException;
use Override;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Application\ProductPage;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Model\ProductTranslation;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DbalProductListing;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SortDirection;

/**
 * The products a channel lists, read page by page on a database: the selection, the offer of a product and of its
 * family, the cursor, the names in a locale.
 *
 * | SKU | Channels | Offer |
 * |---|---|---|
 * | A-DICE | all | full, attributes and a French name |
 * | B-HIDDEN | all | hidden |
 * | C-SHOWN | web | listed, not orderable, an English name |
 * | D-STORE | store | full |
 * | E-WITHDRAWN | all | full, withdrawn |
 * | W-ANNOUNCED | fair | listed, neither orderable nor priced |
 * | W-UNLISTED | fair | by its direct link only |
 * | W-HAT, W-HAT-M | fair | a family by its direct link only, its variant full |
 * | W-MUG, W-MUG-M | fair | a family listed, neither orderable nor priced, its variant full |
 * | SHIRT | all | listed, not orderable, a family of sizes |
 * | SHIRT-M, SHIRT-S | all | full variants, SHIRT-S with an empty French name |
 * | Z-ORPHAN | all | full, a variant of a family not stored |
 */
abstract class ProductListingTestCase extends TestCase
{
    private EntityManager $entityManager;

    private DbalProductListing $listing;

    private QueryLog $queries;

    /**
     * The URL of the database, or `null` to skip.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();
        $this->queries = new QueryLog();

        if (null === $url) {
            self::markTestSkipped('No server is named for this test.');
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $configuration->setMiddlewares([new Middleware($this->queries)]);

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url), $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);
        $this->entityManager->getEventManager()->addEventListener([Events::loadClassMetadata], new TranslationListener());

        $metadata = [$this->entityManager->getClassMetadata(Product::class), $this->entityManager->getClassMetadata(ProductTranslation::class)];
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->listing = new DbalProductListing($connection, DeclaredLocales::fromArray(['fr' => ['en'], 'en' => []]));
        $this->catalogue();
        $this->queries->clear();
    }

    public function testWithoutAChannelOnlyWhatEveryChannelSellsIsListed(): void
    {
        $page = $this->listing->page(null, limit: 20);

        self::assertSame(['A-DICE', 'SHIRT', 'SHIRT-M', 'SHIRT-S'], $this->skus($page));
        self::assertNull($page->next);
    }

    public function testAChannelListsItsSelectionWithTheStrictestOffer(): void
    {
        $page = $this->listing->page('web', limit: 20);
        $notOrderable = Offer::of(Visibility::Listed, false);

        self::assertSame(['A-DICE', 'C-SHOWN', 'SHIRT', 'SHIRT-M', 'SHIRT-S'], $this->skus($page));
        self::assertEquals([Offer::full(), $notOrderable, $notOrderable, $notOrderable, $notOrderable], $this->offers($page));
        self::assertSame(['A-DICE', 'C-SHOWN', 'SHIRT-M', 'SHIRT-S'], $this->skus($this->listing->page('web', limit: 20, families: false)));
    }

    public function testWhatOnlyItsLinkReachesIsNeverListed(): void
    {
        $page = $this->listing->page('fair', limit: 20);
        $announced = Offer::of(Visibility::Listed, false, false);

        self::assertSame(['A-DICE', 'SHIRT', 'SHIRT-M', 'SHIRT-S', 'W-ANNOUNCED', 'W-MUG', 'W-MUG-M'], $this->skus($page));
        self::assertEquals([$announced, $announced, $announced], \array_slice($this->offers($page), 4, 3));
    }

    /**
     * A row that says orderable without its price, written by hand, is read as an offer never orderable.
     */
    public function testARowOrderableWithoutItsPriceIsReadAsNotOrderable(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.DbalProductListing::TABLE.' SET orderable = :yes WHERE sku IN (:skus)',
            ['yes' => true, 'skus' => ['W-ANNOUNCED', 'W-MUG']],
            ['yes' => ParameterType::BOOLEAN, 'skus' => ArrayParameterType::STRING],
        );
        $announced = Offer::of(Visibility::Listed, false, false);

        self::assertEquals([$announced, $announced, $announced], \array_slice($this->offers($this->listing->page('fair', limit: 20)), 4, 3));
    }

    public function testAListedProductCarriesWhatAPageShows(): void
    {
        [$dice, $shirt, $medium] = $this->listing->page(null, limit: 3)->products;

        self::assertSame(['A-DICE', ProductKind::Physical, 'Dice', 1_000, 'CAD', 'goods'], [$dice->sku, $dice->kind, $dice->name, $dice->price->minorUnits, $dice->price->currency->code, $dice->taxCategory]);
        self::assertSame(['brand' => 'Gnome', 'gtin' => '4006381333931'], $dice->attributes);
        self::assertNull($dice->family);
        self::assertSame([], $dice->options);
        self::assertFalse($dice->isFamily());
        self::assertSame(['size'], $shirt->axes);
        self::assertTrue($shirt->isFamily());
        self::assertSame('SHIRT', $medium->family);
        self::assertSame(['size' => 'M'], $medium->options);
        self::assertSame(ProductKind::Digital, $this->listing->page('web', 'B', 1)->products[0]->kind);
    }

    public function testTheCursorReadsOnAcrossPagesAndBatches(): void
    {
        $first = $this->listing->page('store', limit: 2);
        self::assertSame(['A-DICE', 'D-STORE'], $this->skus($first));
        self::assertSame('D-STORE', $first->next);

        $second = $this->listing->page('store', $first->next, 2);
        self::assertSame(['SHIRT', 'SHIRT-M'], $this->skus($second));
        self::assertSame('SHIRT-M', $second->next);

        $third = $this->listing->page('store', $second->next, 2);
        self::assertSame(['SHIRT-S'], $this->skus($third));
        self::assertNull($third->next);
    }

    public function testThePagesReadBackwardsToo(): void
    {
        $first = $this->listing->page('store', limit: 3, direction: SortDirection::Descending);
        self::assertSame(['SHIRT-S', 'SHIRT-M', 'SHIRT'], $this->skus($first));
        self::assertSame('SHIRT', $first->next);

        $second = $this->listing->page('store', $first->next, 3, direction: SortDirection::Descending);
        self::assertSame(['D-STORE', 'A-DICE'], $this->skus($second));
        self::assertNull($second->next);
    }

    public function testAPageReadsTenBatchesAtMost(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $this->entityManager->persist($this->product(\sprintf('P%02d', $i), ['store']));
        }

        $this->entityManager->flush();

        $empty = $this->listing->page('web', 'O', 1);
        self::assertSame([], $empty->products);
        self::assertSame('P10', $empty->next);

        $next = $this->listing->page('web', $empty->next, 1);
        self::assertSame(['SHIRT'], $this->skus($next));
        self::assertSame('SHIRT', $next->next);
    }

    public function testNamesAreReadAlongTheLocalesChain(): void
    {
        self::assertSame(['Dés', 'Shown in English', 'T-shirt', 'T-shirt — M', 'T-shirt — S'], $this->names($this->listing->page('web', limit: 20, locale: 'fr')));
        self::assertSame(['Dice', 'Shown in English', 'T-shirt', 'T-shirt — M', 'T-shirt — S'], $this->names($this->listing->page('web', limit: 20, locale: 'en')));

        foreach ([null, 'de', 'not a locale'] as $locale) {
            self::assertSame(['Dice', 'Shown', 'T-shirt', 'T-shirt — M', 'T-shirt — S'], $this->names($this->listing->page('web', limit: 20, locale: $locale)));
        }

        self::assertSame([], $this->listing->page('web', 'Z', 5, 'fr')->products);
    }

    public function testAPageHoldsOneToFiveHundredProducts(): void
    {
        self::assertCount(5, $this->listing->page('web', limit: 500)->products);
        self::assertCount(1, $this->listing->page('web', limit: 1)->products);

        foreach ([0, 501] as $limit) {
            try {
                $this->listing->page('web', limit: $limit);
                self::fail('The page was read.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('A page lists 1 to 500 products, not %d.', $limit), $invalidArgumentException->getMessage());
            }
        }
    }

    public function testAFullPageOfTheBatchSizeReadsOnFromTheLastRowRead(): void
    {
        $page = $this->listing->page('store', limit: 5);

        self::assertCount(5, $page->products);
        self::assertSame('W-MUG-M', $page->next);
    }

    public function testAPageHoldsFiftyProductsByDefault(): void
    {
        for ($i = 1; $i <= 52; ++$i) {
            $this->entityManager->persist($this->product(\sprintf('P%02d', $i), ['bulk']));
        }

        $this->entityManager->flush();

        $page = $this->listing->page('bulk');

        self::assertCount(50, $page->products);
        self::assertSame('P49', $page->next);
    }

    public function testNamesAreNotReadWithoutAProductOrAChain(): void
    {
        self::assertSame([], $this->listing->page('web', 'Z', 5, 'fr')->products);
        self::assertSame(0, $this->queries->touching(DbalProductListing::TRANSLATIONS));

        $this->listing->page('web', limit: 5);
        self::assertSame(0, $this->queries->touching(DbalProductListing::TRANSLATIONS));

        $this->listing->page('web', limit: 5, locale: 'de');
        self::assertSame(0, $this->queries->touching(DbalProductListing::TRANSLATIONS));

        $this->listing->page('web', limit: 5, locale: 'fr');
        self::assertSame(1, $this->queries->touching(DbalProductListing::TRANSLATIONS));
    }

    public function testTheAttributesAreListedByName(): void
    {
        $this->store('C-SHOWN', 'attributes', '{"gtin":"4006381333931","brand":"Gnome","color":"Red"}');

        self::assertSame(['brand' => 'Gnome', 'color' => 'Red', 'gtin' => '4006381333931'], $this->listing->page('web', 'B', 1)->products[0]->attributes);
    }

    public function testAColumnHoldsScalarsOnly(): void
    {
        $this->store('C-SHOWN', 'axes', '["size",null,["x"],3,true]');
        $this->store('C-SHOWN', 'options', '{"size":"M","gauge":3,"fine":true,"nested":{"x":1},"none":null}');

        $product = $this->listing->page('web', 'B', 1)->products[0];

        self::assertSame(['size', '3', '1'], $product->axes);
        // MySQL gives the keys of a JSON object in an order of its own.
        self::assertEqualsCanonicalizing(['size' => 'M', 'gauge' => '3', 'fine' => '1'], $product->options);
    }

    public function testAColumnMayNestThreeLevels(): void
    {
        $this->store('C-SHOWN', 'axes', '[[[1]]]');
        $this->store('C-SHOWN', 'options', '{"a":{"b":{"c":1}}}');

        $product = $this->listing->page('web', 'B', 1)->products[0];

        self::assertSame([], $product->axes);
        self::assertSame([], $product->options);
    }

    #[DataProvider('tooDeepColumns')]
    public function testAColumnNestingFourLevelsIsRefused(string $column, string $json): void
    {
        $this->store('C-SHOWN', $column, $json);

        $this->expectException(JsonException::class);

        $this->listing->page('web', 'B', 1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tooDeepColumns(): iterable
    {
        yield 'axes' => ['axes', '[[[[1]]]]'];
        yield 'options' => ['options', '{"a":{"b":{"c":{"d":1}}}}'];
    }

    private function store(string $sku, string $column, string $json): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE '.DbalProductListing::TABLE.' SET '.$column.' = :json WHERE sku = :sku', ['json' => $json, 'sku' => $sku]);
    }

    private function catalogue(): void
    {
        $dice = $this->product('A-DICE');
        $dice->describe('brand', 'Gnome');
        $dice->describe('gtin', '4006381333931');

        $hidden = $this->product('B-HIDDEN');
        $hidden->changeOffer(Offer::hidden());

        $shown = Product::create('C-SHOWN', ProductKind::Digital, 'Shown', Money::of(500, Currency::of('CAD')), 'books');
        $shown->sellThrough(['web']);
        $shown->changeOffer(Offer::of(Visibility::Listed, false));

        $withdrawn = $this->product('E-WITHDRAWN');
        $withdrawn->withdraw();

        $announced = $this->product('W-ANNOUNCED', ['fair']);
        $announced->changeOffer(Offer::of(Visibility::Listed, false, false));

        $unlisted = $this->product('W-UNLISTED', ['fair']);
        $unlisted->changeOffer(Offer::of(Visibility::Unlisted));

        $hat = $this->product('W-HAT', ['fair']);
        $hat->defineAxes(['size']);
        $hat->changeOffer(Offer::of(Visibility::Unlisted));

        $hatMedium = Product::variant($hat, 'W-HAT-M', ['size' => 'M']);
        $hatMedium->changeOffer(Offer::full());

        $mug = $this->product('W-MUG', ['fair']);
        $mug->defineAxes(['size']);
        $mug->changeOffer(Offer::of(Visibility::Listed, false, false));

        $mugMedium = Product::variant($mug, 'W-MUG-M', ['size' => 'M']);
        $mugMedium->changeOffer(Offer::full());

        $shirt = $this->product('SHIRT', [], 'T-shirt');
        $shirt->defineAxes(['size']);

        $medium = Product::variant($shirt, 'SHIRT-M', ['size' => 'M']);
        $small = Product::variant($shirt, 'SHIRT-S', ['size' => 'S']);

        $shirt->changeOffer(Offer::of(Visibility::Listed, false));
        $gone = $this->product('GONE');
        $gone->defineAxes(['size']);

        $orphan = Product::variant($gone, 'Z-ORPHAN', ['size' => 'M']);

        foreach ([$dice, $hidden, $shown, $this->product('D-STORE', ['store']), $withdrawn, $announced, $unlisted, $hat, $hatMedium, $mug, $mugMedium, $shirt, $medium, $small, $orphan] as $product) {
            $this->entityManager->persist($product);
        }

        $this->translate($dice, 'fr', 'Dés');
        $this->translate($shown, 'en', 'Shown in English');
        $this->entityManager->persist(new ProductTranslation($small, Locale::fromString('fr')));
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $channels
     */
    private function product(string $sku, array $channels = [], string $name = 'Dice'): Product
    {
        $product = Product::create($sku, ProductKind::Physical, $name, Money::of(1_000, Currency::of('CAD')), 'goods');
        $product->sellThrough($channels);

        return $product;
    }

    private function translate(Product $product, string $locale, string $name): void
    {
        $translation = new ProductTranslation($product, Locale::fromString($locale));
        $translation->rename($name);

        $this->entityManager->persist($translation);
    }

    /**
     * @return list<string>
     */
    private function skus(ProductPage $page): array
    {
        return array_map(static fn (ListedProduct $product): string => $product->sku, $page->products);
    }

    /**
     * @return list<Offer>
     */
    private function offers(ProductPage $page): array
    {
        return array_map(static fn (ListedProduct $product): Offer => $product->offer, $page->products);
    }

    /**
     * @return list<string>
     */
    private function names(ProductPage $page): array
    {
        return array_map(static fn (ListedProduct $product): string => $product->name, $page->products);
    }
}
