<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Infrastructure\Taxonomy\TaxonomyProductClassification;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Products filed in the categories and tags of `phil/taxonomy-bundle`, on its own tables.
 */
#[CoversClass(TaxonomyProductClassification::class)]
final class TaxonomyProductClassificationTest extends TestCase
{
    private EntityManager $manager;

    private Taxonomy $taxonomy;

    private TaxonomyProductClassification $classification;

    private Product $dice;

    #[Override]
    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname((string) new ReflectionClass(Tag::class)->getFileName())]));
        $config->enableNativeLazyObjects(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Phil\CommerceCatalogBundle\Tests\TaxonomyProxies');

        $this->manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->manager)->createSchema($this->manager->getMetadataFactory()->getAllMetadata());

        $repository = new DoctrineTaxonomyRepository($this->manager);
        $this->taxonomy = new Taxonomy($repository);
        $this->classification = new TaxonomyProductClassification($this->taxonomy, $repository);
        $this->dice = Product::create('DICE', ProductKind::Physical, 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods');
    }

    public function testAProductIsTagged(): void
    {
        self::assertSame(['Dice', 'Gifts'], $this->sorted($this->classification->tag($this->dice, ['Dice', 'Gifts', '!!!'])));
        $this->manager->flush();

        self::assertSame(['Dice', 'Gifts'], $this->sorted($this->classification->tagsOf($this->dice)));
        self::assertSame(['Dice'], $this->classification->tag($this->dice, ['dice']));
        $this->manager->flush();
        self::assertSame(['Dice'], $this->classification->tagsOf($this->dice));
        self::assertSame(['Gifts', 'Maps'], $this->sorted($this->classification->tag($this->dice, ['!!!', 'Maps', '###', 'Gifts'])));
    }

    public function testAProductIsFiledInCategories(): void
    {
        $games = $this->taxonomy->createCategory('games');
        $this->taxonomy->createCategory('dice', $games);
        $this->taxonomy->createCategory('books');

        $this->manager->flush();

        $this->classification->categorize($this->dice, ['dice', 'books', 'dice']);
        $this->manager->flush();
        $book = Product::create('BOOK', ProductKind::Physical, 'Book', Money::of(1_000, Currency::of('CAD')), 'goods');
        $this->classification->categorize($book, ['books']);
        $this->manager->flush();

        self::assertSame(['books', 'dice'], $this->sorted($this->classification->categoriesOf($this->dice)));
        self::assertSame(['DICE'], $this->classification->inCategory('games'));
        self::assertSame(['BOOK', 'DICE'], $this->sorted($this->classification->inCategory('books')));

        $this->classification->tag($this->dice, ['Dice']);
        $this->manager->flush();
        $this->classification->forget($this->dice);
        $this->manager->flush();
        self::assertSame([], $this->classification->categoriesOf($this->dice));
        self::assertSame([], $this->classification->tagsOf($this->dice));
        self::assertSame(['books'], $this->classification->categoriesOf($book));
    }

    public function testAnUnknownCategoryIsRefused(): void
    {
        $this->expectExceptionObject(CatalogRefused::unknownCategory('nowhere'));

        $this->classification->categorize($this->dice, ['nowhere']);
    }

    public function testAnUnknownCategoryHoldsNothing(): void
    {
        $this->expectExceptionObject(CatalogRefused::unknownCategory('nowhere'));

        $this->classification->inCategory('nowhere');
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
