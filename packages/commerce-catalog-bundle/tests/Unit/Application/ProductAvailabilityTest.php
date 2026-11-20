<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\ProductAvailability;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductAvailability::class)]
final class ProductAvailabilityTest extends TestCase
{
    public function testAProductUnknownOrOfAnotherTypeIsLeftToOthers(): void
    {
        $products = new InMemoryProducts();

        self::assertEquals(Offer::full(), new ProductAvailability($products)->offerThrough(SellableReference::of('product', 'NONE'), 'web'));
        self::assertEquals(Offer::full(), new ProductAvailability($products)->offerThrough(SellableReference::of('offer', 'NONE'), 'web'));
    }

    public function testAProductAnswersItsOwnVisibilityInTheChannel(): void
    {
        $dice = $this->product('DICE');
        $dice->changeOffer(Offer::of(Visibility::Listed, false));

        $products = new InMemoryProducts($dice);

        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->offerThrough(SellableReference::of('product', 'DICE'), 'web'));
        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->of($dice, null));

        $dice->withdraw();
        self::assertEquals(Offer::hidden(), new ProductAvailability($products)->of($dice, 'web'));
    }

    public function testAVariantIsMadeStricterByItsFamily(): void
    {
        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size']);

        $medium = Product::variant($shirt, 'SHIRT-M', ['size' => 'M']);
        $small = Product::variant($shirt, 'SHIRT-S', ['size' => 'S']);
        $small->changeOffer(Offer::of(Visibility::Listed, false));

        $products = new InMemoryProducts($shirt, $medium, $small);

        self::assertEquals(Offer::full(), new ProductAvailability($products)->of($medium, 'web'));
        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->of($small, 'web'));

        $shirt->changeOffer(Offer::of(Visibility::Listed, false));
        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->of($medium, 'web'));
        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->offerThrough(SellableReference::of('product', 'SHIRT-M'), 'web'));

        $shirt->sellThrough(['store']);
        self::assertEquals(Offer::hidden(), new ProductAvailability($products)->of($medium, 'web'));
        self::assertEquals(Offer::of(Visibility::Listed, false), new ProductAvailability($products)->of($medium, 'store'));

        $shirt->withdraw();
        self::assertEquals(Offer::hidden(), new ProductAvailability($products)->of($medium, 'store'));
    }

    public function testAFamilyLeftToItsLinkLeavesItsVariantsThereToo(): void
    {
        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size']);

        $medium = Product::variant($shirt, 'SHIRT-M', ['size' => 'M']);
        $shirt->changeOffer(Offer::of(Visibility::Unlisted, false, false));

        self::assertEquals(Offer::of(Visibility::Unlisted, false, false), new ProductAvailability(new InMemoryProducts($shirt, $medium))->of($medium, 'web'));
    }

    public function testAVariantWhoseFamilyIsGoneKeepsItsOwnVisibility(): void
    {
        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size']);

        $medium = Product::variant($shirt, 'SHIRT-M', ['size' => 'M']);
        $shirt->changeOffer(Offer::hidden());

        self::assertEquals(Offer::full(), new ProductAvailability(new InMemoryProducts($medium))->of($medium, 'web'));
        self::assertEquals(Offer::hidden(), new ProductAvailability(new InMemoryProducts($shirt, $medium))->of($medium, 'web'));
    }

    private function product(string $sku): Product
    {
        return Product::create($sku, ProductKind::Physical, $sku, Money::of(1_000, Currency::of('CAD')), 'goods');
    }
}
