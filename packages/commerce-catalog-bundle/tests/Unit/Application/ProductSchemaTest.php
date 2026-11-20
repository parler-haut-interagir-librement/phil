<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Phil\CommerceCatalogBundle\Application\ProductSchema;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductSchema::class)]
final class ProductSchemaTest extends TestCase
{
    public function testAProductIsAProductWithItsOffer(): void
    {
        $product = Product::create('DICE-D20', ProductKind::Physical, 'Dé à 20 faces', Money::of(499, Currency::of('CAD')), 'goods');
        $product->describe('colour', 'rouge');
        $product->describe('material', 'résine');

        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Dé à 20 faces',
            'sku' => 'DICE-D20',
            'url' => 'https://shop.example.com/d20',
            'additionalProperty' => [['@type' => 'PropertyValue', 'name' => 'colour', 'value' => 'rouge'], ['@type' => 'PropertyValue', 'name' => 'material', 'value' => 'résine']],
            'offers' => ['@type' => 'Offer', 'price' => '4.99', 'priceCurrency' => 'CAD', 'availability' => 'https://schema.org/InStock', 'url' => 'https://shop.example.com/d20'],
        ], ProductSchema::of($product, 'https://shop.example.com/d20'));

        $plain = Product::create('MAP', ProductKind::Digital, 'Carte', Money::of(1000, Currency::of('CAD')), 'goods');
        $plain->withdraw();
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Carte', 'sku' => 'MAP', 'offers' => ['@type' => 'Offer', 'price' => '7.50', 'priceCurrency' => 'CAD', 'availability' => 'https://schema.org/Discontinued']], ProductSchema::of($plain, price: Money::of(750, Currency::of('CAD'))));
    }

    /**
     * A variant names its family and lists its options with its attributes; the name given, in the page's locale,
     * replaces its own.
     */
    public function testAVariantNamesItsFamily(): void
    {
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $shirt->defineAxes(['size']);

        $variant = Product::variant($shirt, 'SHIRT-M', ['size' => 'M']);
        $variant->describe('material', 'cotton');

        $schema = ProductSchema::of($variant, name: 'T-shirt M');

        self::assertSame('T-shirt M', $schema['name']);
        self::assertSame('SHIRT', $schema['inProductGroupWithID']);
        self::assertSame([['@type' => 'PropertyValue', 'name' => 'material', 'value' => 'cotton'], ['@type' => 'PropertyValue', 'name' => 'size', 'value' => 'M']], $schema['additionalProperty']);
    }

    public function testTheAttributesGivenReplaceTheProductsOwn(): void
    {
        $shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $shirt->describe('material', 'cotton');

        self::assertSame([['@type' => 'PropertyValue', 'name' => 'fit', 'value' => 'slim']], ProductSchema::of($shirt, attributes: ['fit' => 'slim'])['additionalProperty']);
        self::assertArrayNotHasKey('additionalProperty', ProductSchema::of($shirt, attributes: []));
    }
}
