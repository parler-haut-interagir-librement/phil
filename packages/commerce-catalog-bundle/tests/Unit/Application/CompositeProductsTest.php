<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeProducts::class)]
final class CompositeProductsTest extends TestCase
{
    private InMemoryProducts $products;

    private CompositeProducts $composites;

    protected function setUp(): void
    {
        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size']);

        $this->products = new InMemoryProducts($this->product('BOX'), $this->product('BOOK'), $this->product('DICE'), $shirt);
        $this->composites = new CompositeProducts($this->products);
    }

    public function testACompositeIsMadeOfProductsOfTheCatalogue(): void
    {
        $box = $this->products->find('BOX') ?? self::fail('No box.');
        $this->composites->compose($box, [ProductComponent::of('BOOK'), ProductComponent::of('DICE', 2)]);

        self::assertSame([['BOOK', 1], ['DICE', 2]], $this->parts(SellableReference::of('product', 'BOX')));
        self::assertSame([], $this->parts(SellableReference::of('product', 'BOOK')));
        self::assertSame([], $this->parts(SellableReference::of('product', 'NOWHERE')));
        self::assertSame([], $this->parts(SellableReference::of('offer', 'BOX')));

        $this->composites->compose($box, []);
        self::assertSame([], $this->parts(SellableReference::of('product', 'BOX')));
    }

    public function testAnUnknownPartIsRefused(): void
    {
        $this->expectExceptionObject(CatalogRefused::unknownPart('BOX', 'MEEPLE'));

        $this->composites->compose($this->products->find('BOX') ?? self::fail('No box.'), [ProductComponent::of('BOOK'), ProductComponent::of('MEEPLE')]);
    }

    public function testAFamilyIsNoPart(): void
    {
        $this->expectExceptionObject(CatalogRefused::nestedPart('BOX', 'SHIRT'));

        $this->composites->compose($this->products->find('BOX') ?? self::fail('No box.'), [ProductComponent::of('SHIRT')]);
    }

    public function testACompositeIsNoPart(): void
    {
        $box = $this->products->find('BOX') ?? self::fail('No box.');
        $this->composites->compose($box, [ProductComponent::of('BOOK')]);
        $crate = $this->product('CRATE');
        $this->products->add($crate);

        try {
            $this->composites->compose($crate, [ProductComponent::of('DICE'), ProductComponent::of('BOX')]);
            self::fail('A composite was made of a composite.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame(CatalogRefused::nestedPart('CRATE', 'BOX')->getMessage(), $catalogRefused->getMessage());
        }

        self::assertFalse($crate->isComposite());
    }

    /**
     * @return list<array{string, int}>
     */
    private function parts(SellableReference $reference): array
    {
        return array_map(static fn (ProductComponent $part): array => [$part->sku, $part->quantity], $this->composites->partsOf($reference));
    }

    private function product(string $sku): Product
    {
        return Product::create($sku, ProductKind::Physical, $sku, Money::of(1_000, Currency::of('CAD')), 'goods');
    }
}
