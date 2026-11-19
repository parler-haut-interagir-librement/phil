<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Phil\CommerceCatalogBundle\Application\ProductVariants;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductVariants::class)]
final class ProductVariantsTest extends TestCase
{
    private InMemoryProducts $products;

    private ProductVariants $variants;

    private Product $shirt;

    protected function setUp(): void
    {
        $this->shirt = Product::create('SHIRT', ProductKind::Physical, 'T-shirt', Money::of(2_500, Currency::of('CAD')), 'goods');
        $this->shirt->defineAxes(['size', 'colour']);

        $this->products = new InMemoryProducts($this->shirt, Product::create('MUG', ProductKind::Physical, 'Mug', Money::of(1_200, Currency::of('CAD')), 'goods'));
        $this->variants = new ProductVariants($this->products, $this->products);
    }

    public function testVariantsAreCreatedAndStored(): void
    {
        $red = $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red']);
        $blue = $this->variants->create($this->shirt, 'SHIRT-L-BLUE', ['colour' => 'blue', 'size' => 'L'], Money::of(2_700, Currency::of('CAD')), 'Blue T-shirt');

        self::assertSame($red, $this->products->find('SHIRT-M-RED'));
        self::assertSame('Blue T-shirt', $blue->name());
        self::assertSame(['SHIRT-L-BLUE', 'SHIRT-M-RED'], array_map(static fn (Product $variant): string => $variant->sku, $this->variants->variantsOf($this->shirt)));
    }

    public function testAVariantIsFoundByItsValuesInAnyOrder(): void
    {
        $red = $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red']);
        $this->variants->create($this->shirt, 'SHIRT-L-RED', ['size' => 'L', 'colour' => 'red']);

        self::assertSame($red, $this->variants->find($this->shirt, ['colour' => 'red', 'size' => 'M']));
        self::assertSame($red, $this->variants->find($this->shirt, ['size' => 'M', 'colour' => 'red']));
        self::assertNull($this->variants->find($this->shirt, ['size' => 'S', 'colour' => 'red']));
        self::assertNull($this->variants->find($this->shirt, ['size' => 'M']));

        $red->withdraw();
        self::assertNull($this->variants->find($this->shirt, ['size' => 'M', 'colour' => 'red']));
        self::assertSame($red, $this->variants->find($this->shirt, ['size' => 'M', 'colour' => 'red'], withdrawn: true));
    }

    public function testTheChoicesAreThoseOfTheVariantsStillSold(): void
    {
        $this->variants->create($this->shirt, 'SHIRT-A', ['size' => 'M', 'colour' => 'red']);
        $this->variants->create($this->shirt, 'SHIRT-B', ['size' => 'L', 'colour' => 'red']);
        $this->variants->create($this->shirt, 'SHIRT-C', ['size' => 'M', 'colour' => 'blue']);
        $this->variants->create($this->shirt, 'SHIRT-D', ['size' => 'XL', 'colour' => 'green'])->withdraw();

        self::assertSame(['size' => ['M', 'L'], 'colour' => ['red', 'blue']], $this->variants->choices($this->shirt));
        self::assertSame([], $this->variants->choices($this->products->find('MUG') ?? $this->shirt));
    }

    public function testAWithdrawnVariantFirstDoesNotStopTheChoices(): void
    {
        $this->variants->create($this->shirt, 'SHIRT-A', ['size' => 'XL', 'colour' => 'green'])->withdraw();
        $this->variants->create($this->shirt, 'SHIRT-B', ['size' => 'M', 'colour' => 'red']);
        $this->variants->create($this->shirt, 'SHIRT-C', ['size' => 'L', 'colour' => 'blue']);

        self::assertSame(['size' => ['M', 'L'], 'colour' => ['red', 'blue']], $this->variants->choices($this->shirt));
    }

    public function testAVariantWithTheSameValuesIsRefused(): void
    {
        $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red'])->withdraw();

        $this->expectExceptionObject(CatalogRefused::sameOptions('SHIRT', ['size' => 'M', 'colour' => 'red']));

        try {
            $this->variants->create($this->shirt, 'SHIRT-M-RED-2', ['colour' => 'red', 'size' => 'M']);
        } finally {
            self::assertNull($this->products->find('SHIRT-M-RED-2'));
        }
    }

    public function testATakenSkuIsRefused(): void
    {
        $this->expectExceptionObject(CatalogRefused::taken('MUG'));

        $this->variants->create($this->shirt, 'MUG', ['size' => 'M', 'colour' => 'red']);
    }

    public function testAVariantShowsItsFamilysAttributesUnderItsOwn(): void
    {
        $this->shirt->describe('material', 'cotton');
        $this->shirt->describe('fit', 'regular');

        $red = $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red']);
        $red->describe('fit', 'slim');
        $red->describe('care', 'cold wash');

        $mug = $this->products->find('MUG');
        self::assertNotNull($mug);
        $mug->describe('volume', '350 ml');

        self::assertSame(['care' => 'cold wash', 'fit' => 'slim', 'material' => 'cotton'], $this->variants->attributesOf($red));
        self::assertSame(['fit' => 'regular', 'material' => 'cotton'], $this->variants->attributesOf($this->shirt));
        self::assertSame(['volume' => '350 ml'], $this->variants->attributesOf($mug));
    }

    public function testAFamilyPassesItsDecisionsDownToItsVariants(): void
    {
        $red = $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red'], Money::of(2_700, Currency::of('CAD')), 'Red shirt');
        $blue = $this->variants->create($this->shirt, 'SHIRT-M-BLUE', ['size' => 'M', 'colour' => 'blue']);
        $this->shirt->classify('clothing');
        $this->shirt->sellThrough(['web']);
        $this->shirt->trackSerialNumbers();

        $blue->classify('clothing');
        $blue->sellThrough(['web']);
        $blue->trackSerialNumbers();

        self::assertSame([$red], $this->variants->cascade($this->shirt));
        self::assertSame(['clothing', ['web'], true, 'Red shirt', 2_700], [$red->taxCategory(), $red->channels(), $red->tracksSerialNumbers(), $red->name(), $red->price->minorUnits]);
        self::assertSame([], $this->variants->cascade($this->shirt));

        $this->shirt->sellThrough([]);
        self::assertSame([$blue, $red], $this->variants->cascade($this->shirt));
        self::assertSame([], $blue->channels());

        $this->shirt->trackSerialNumbers(false);
        self::assertSame([$blue, $red], $this->variants->cascade($this->shirt));
        self::assertFalse($red->tracksSerialNumbers());

        $this->shirt->classify('goods');
        self::assertSame([$blue, $red], $this->variants->cascade($this->shirt));
        self::assertSame('goods', $blue->taxCategory());
    }

    public function testAFamilyIsShownWithTheRangeOfItsVariantsStillSold(): void
    {
        self::assertNull($this->variants->priceRange($this->shirt));

        $this->variants->create($this->shirt, 'SHIRT-M-RED', ['size' => 'M', 'colour' => 'red'], Money::of(2_700, Currency::of('CAD')));
        self::assertSame([2_700, 2_700], $this->range());

        $this->variants->create($this->shirt, 'SHIRT-S-RED', ['size' => 'S', 'colour' => 'red'], Money::of(2_200, Currency::of('CAD')));
        $this->variants->create($this->shirt, 'SHIRT-L-RED', ['size' => 'L', 'colour' => 'red'], Money::of(3_100, Currency::of('CAD')));
        $this->variants->create($this->shirt, 'SHIRT-XL-RED', ['size' => 'XL', 'colour' => 'red'], Money::of(2_500, Currency::of('CAD')));

        $first = $this->variants->create($this->shirt, 'SHIRT-A-RED', ['size' => 'A', 'colour' => 'red'], Money::of(100, Currency::of('CAD')));
        $first->withdraw();

        $gone = $this->variants->create($this->shirt, 'SHIRT-XS-RED', ['size' => 'XS', 'colour' => 'red'], Money::of(100, Currency::of('CAD')));
        $gone->withdraw();

        self::assertSame([2_200, 3_100], $this->range());
    }

    /**
     * @return array{int, int}|null
     */
    private function range(): ?array
    {
        $range = $this->variants->priceRange($this->shirt);

        return null === $range ? null : [$range[0]->minorUnits, $range[1]->minorUnits];
    }
}
