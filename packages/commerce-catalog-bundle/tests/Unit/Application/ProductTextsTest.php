<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Doctrine\ORM\EntityManager;
use LogicException;
use Override;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\PriceRuleSellables;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Application\ProductTexts;
use Phil\CommerceCatalogBundle\Application\ProductTextSet;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrineProductRepository;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\StoredDatabase;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The names of products read from stored translations, and the lines described in the buyer's locale.
 */
#[CoversClass(ProductTexts::class)]
#[CoversClass(ProductTextSet::class)]
#[CoversClass(ProductSellables::class)]
#[CoversClass(PriceRuleSellables::class)]
#[CoversClass(DoctrineProductRepository::class)]
final class ProductTextsTest extends TestCase
{
    private EntityManager $manager;

    private LocaleAwareInterface $current;

    private ProductTexts $texts;

    private Product $dice;

    private Product $map;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = StoredDatabase::entityManager();
        $this->current = new class implements LocaleAwareInterface {
            public string $locale = 'fr_CA';

            #[Override]
            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            #[Override]
            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $this->texts = new ProductTexts(new Translations($this->manager), DeclaredLocales::fromArray(['en' => [], 'fr' => ['en'], 'fr_CA' => ['fr']]), $this->current);

        $this->dice = Product::create('DICE', ProductKind::Physical, 'Dice set', Money::of(2_000, Currency::of('CAD')), 'goods');
        $this->map = Product::create('123', ProductKind::Digital, 'Map', Money::of(500, Currency::of('CAD')), 'goods');
        $this->manager->persist($this->dice);
        $this->manager->persist($this->map);
        $this->texts->translate($this->dice, 'fr')->rename('Jeu de dés');
        $this->texts->translate($this->map, 'fr');
        $this->manager->flush();
    }

    public function testANameIsReadAlongTheChain(): void
    {
        $set = $this->texts->of([$this->dice, $this->map]);

        self::assertSame('Jeu de dés', $set->name($this->dice));
        self::assertTrue($set->isTranslated($this->dice));
        self::assertSame('Map', $set->name($this->map));
        self::assertFalse($set->isTranslated($this->map));

        self::assertSame('Dice set', $this->texts->of([$this->dice], 'en')->name($this->dice));
        self::assertSame('Dice set', $this->texts->of([$this->dice], 'de')->name($this->dice));
        self::assertSame('Dice set', $this->texts->of([$this->dice], 'not a locale!')->name($this->dice));
        self::assertSame('Dice set', $this->texts->of([])->name($this->dice));
        self::assertSame(['fr'], $this->texts->locales($this->dice));
    }

    public function testATranslationIsWrittenInItsOwnLocale(): void
    {
        $english = $this->texts->translate($this->dice, 'en');

        self::assertSame('', $english->name());
        self::assertSame($english, $this->texts->translate($this->dice, 'en'));
        self::assertSame('Jeu de dés', $this->texts->translate($this->dice, 'fr')->name());
    }

    public function testWithoutTranslationsEveryNameIsItsOwn(): void
    {
        $texts = new ProductTexts();

        self::assertSame('Dice set', $texts->of([$this->dice], 'fr')->name($this->dice));
        self::assertSame([], $texts->locales($this->dice));
        self::assertSame('Dice set', new ProductTexts(new Translations($this->manager))->of([$this->dice], 'fr')->name($this->dice));
        self::assertSame('Dice set', new ProductTexts(new Translations($this->manager), DeclaredLocales::fromArray(['fr' => []]))->of([$this->dice])->name($this->dice));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The products are translated with phil/doctrine-behaviours and phil_commerce_catalog.translations.locales: declare the locales served.');

        $texts->translate($this->dice, 'fr');
    }

    public function testTheVariantsOfAFamilyAreReadInTheOrderOfTheirSkus(): void
    {
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $shirt->defineAxes(['size']);

        $this->manager->persist($shirt);
        $this->manager->persist(Product::variant($shirt, 'SHIRT-Z', ['size' => 'XL']));
        $this->manager->persist(Product::variant($shirt, 'SHIRT-A', ['size' => 'M']));
        $this->manager->flush();
        $this->manager->clear();

        $skus = array_map(static fn (Product $variant): string => $variant->sku, new DoctrineProductRepository($this->manager)->variantsOf('SHIRT'));

        self::assertSame(['SHIRT-A', 'SHIRT-Z'], $skus);
    }

    public function testALineIsDescribedInTheBuyersLocale(): void
    {
        $sellables = new ProductSellables(new DoctrineProductRepository($this->manager), $this->texts);

        self::assertSame('Jeu de dés', $sellables->resolve(SellableReference::of('product', 'DICE'))?->description);

        $this->current->setLocale('en');
        $english = $sellables->resolve(SellableReference::of('product', 'DICE'));
        self::assertNotNull($english);
        self::assertSame('Dice set', $english->description);
        self::assertSame([], new DoctrineProductRepository($this->manager)->variantsOf('DICE'));
    }

    public function testThePriceRulesDescribeTheirLinesInTheBuyersLocale(): void
    {
        $this->dice->payWhatYouWant(null, Money::of(5_000, Currency::of('CAD')));
        $this->dice->offerCommunityCopies(Money::of(500, Currency::of('CAD')));

        $sellables = new PriceRuleSellables(new InMemoryProducts($this->dice), $this->texts);

        self::assertSame('Jeu de dés', $sellables->resolve(PriceRuleSellables::reference($this->dice))?->description);
        self::assertSame('Community copy of Jeu de dés', $sellables->resolve(SellableReference::of('community_copy', 'DICE'))?->description);

        $this->current->setLocale('en');
        self::assertSame('Dice set', $sellables->resolve(PriceRuleSellables::reference($this->dice))?->description);
    }
}
