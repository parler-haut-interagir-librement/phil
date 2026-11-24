<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Domain;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Model\ProductTranslation;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the products, their translations, the price books and the
 * community copies.
 */
#[CoversClass(Product::class)]
#[CoversClass(ProductTranslation::class)]
#[CoversClass(PriceBook::class)]
#[CoversClass(CommunityCopyFunding::class)]
#[CoversClass(CommunityCopyClaim::class)]
final class MappingTest extends TestCase
{
    public function testTheProductTable(): void
    {
        $metadata = $this->metadata(Product::class);

        self::assertSame('phil_commerce_catalog_product', $metadata->getTableName());
        self::assertSame(['sku'], $metadata->getIdentifierFieldNames());
        self::assertSame([['columns' => ['active']], ['columns' => ['family']]], array_values($metadata->table['indexes'] ?? []));
        self::assertSame([
            'priceMinorUnits' => ['price', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'attributes' => ['attributes', 'json', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'visibility' => ['visibility', 'string', 16, false, null],
            'orderable' => ['orderable', 'boolean', null, false, null],
            'priceShown' => ['price_shown', 'boolean', null, false, null],
            'channels' => ['channels', 'json', null, false, null],
            'family' => ['family', 'string', 64, true, null],
            'axes' => ['axes', 'json', null, false, null],
            'options' => ['options', 'json', null, false, null],
            'components' => ['components', 'json', null, false, null],
            'serialNumbers' => ['serial_numbers', 'boolean', null, false, null],
            'payWhatYouWant' => ['pay_what_you_want', 'boolean', null, false, null],
            'suggestedPrice' => ['suggested_price', 'bigint', null, true, null],
            'maximumPrice' => ['maximum_price', 'bigint', null, true, null],
            'communityCopyPrice' => ['community_copy_price', 'bigint', null, true, null],
            'suggestedRetailPrice' => ['suggested_retail_price', 'bigint', null, true, null],
            'sku' => ['sku', 'string', 64, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'name' => ['name', 'string', 255, false, null],
            'taxCategory' => ['tax_category', 'string', 64, false, null],
        ], $this->columns($metadata));
        self::assertSame(ProductKind::class, $metadata->fieldMappings['kind']->enumType);
        self::assertSame(Visibility::class, $metadata->fieldMappings['visibility']->enumType);
        self::assertSame(['default' => 'listed'], $metadata->fieldMappings['visibility']->options);
        self::assertSame(['default' => true], $metadata->fieldMappings['orderable']->options);
        self::assertSame(['default' => true], $metadata->fieldMappings['priceShown']->options);
    }

    public function testThePriceBookTable(): void
    {
        $metadata = $this->metadata(PriceBook::class);

        self::assertSame('phil_commerce_catalog_price_book', $metadata->getTableName());
        self::assertSame(['code'], $metadata->getIdentifierFieldNames());
        self::assertSame([PriceBook::ACTIVE_INDEX => ['columns' => ['active']]], $metadata->table['indexes'] ?? []);
        self::assertSame([
            'active' => ['active', 'boolean', null, false, null],
            'tiers' => ['tiers', 'json', null, false, null],
            'after' => ['after_books', 'json', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'customerGroup' => ['customer_group', 'string', 64, true, null],
            'channel' => ['channel', 'string', 64, true, null],
            'startsAt' => ['starts_at', 'datetime_utc_immutable', null, true, null],
            'endsAt' => ['ends_at', 'datetime_utc_immutable', null, true, null],
        ], $this->columns($metadata));
    }

    public function testTheCommunityCopyTables(): void
    {
        $funding = $this->metadata(CommunityCopyFunding::class);
        $claim = $this->metadata(CommunityCopyClaim::class);

        self::assertSame('phil_commerce_catalog_community_copy_funding', $funding->getTableName());
        self::assertSame(['sku', 'orderNumber'], $funding->getIdentifierFieldNames());
        self::assertSame([
            'sku' => ['sku', 'string', 64, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'amount' => ['amount', 'bigint', null, false, null],
            'fundedAt' => ['funded_at', 'datetime_utc_immutable', null, false, null],
        ], $this->columns($funding));
        self::assertSame('phil_commerce_catalog_community_copy_claim', $claim->getTableName());
        self::assertSame(['sku', 'claimant'], $claim->getIdentifierFieldNames());
        self::assertSame([
            'sku' => ['sku', 'string', 64, false, null],
            'claimant' => ['claimant', 'string', 64, false, null],
            'claimedAt' => ['claimed_at', 'datetime_utc_immutable', null, false, null],
        ], $this->columns($claim));
    }

    public function testTheTranslationTable(): void
    {
        $metadata = $this->metadata(ProductTranslation::class);

        self::assertSame('phil_commerce_catalog_product_translation', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame(['phil_commerce_catalog_product_translation_translatable_locale' => ['columns' => ['product', 'locale']]], $metadata->table['uniqueConstraints'] ?? []);
        self::assertSame([
            'locale' => ['locale', 'string', 12, false, null],
            'id' => ['id', 'integer', null, false, null],
            'name' => ['name', 'string', 255, false, null],
        ], $this->columns($metadata));
        $join = $metadata->getAssociationMapping('translatable');
        self::assertSame('SKU', new ProductTranslation(Product::create('SKU', ProductKind::Physical, 'Name', Money::zero(Currency::of('CAD')), 'goods'), Locale::fromString('fr'))->translatable()->sku);
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $join);
        self::assertSame(Product::class, $join->targetEntity);
        self::assertSame([['product', 'sku', false, 'CASCADE']], array_map(static fn (JoinColumnMapping $column): array => [$column->name, $column->referencedColumnName, $column->nullable, $column->onDelete], $join->joinColumns));
    }

    public function testWhatTheyHoldFitsTheirColumns(): void
    {
        $product = Product::create(str_repeat('A', 64), ProductKind::Physical, str_repeat('n', 255), Money::zero(Currency::of('CAD')), str_repeat('g', 64));
        $book = PriceBook::create(str_repeat('b', 64), str_repeat('n', 255), Currency::of('CAD'));
        $products = $this->metadata(Product::class)->fieldMappings;
        $books = $this->metadata(PriceBook::class)->fieldMappings;

        self::assertSame($products['sku']->length, \strlen($product->sku));
        self::assertSame($products['name']->length, \strlen($product->name()));
        self::assertSame($products['taxCategory']->length, \strlen($product->taxCategory()));
        self::assertSame($books['code']->length, \strlen($book->code));
        self::assertSame($books['name']->length, \strlen($book->name));
    }

    /**
     * @template T of object
     *
     * @param ClassMetadata<T> $metadata
     *
     * @return array<string, array{string, string, ?int, ?bool, mixed}>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return $columns;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
