<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Internal\Check;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(ProductBundle::class)]
#[CoversClass(BundleComponent::class)]
#[CoversClass(BundleKind::class)]
#[CoversClass(PricePolicy::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidBundle::class)]
final class ProductBundleTest extends TestCase
{
    public function testABundleKeepsItsComponentsInOrder(): void
    {
        $bundle = self::starter();

        self::assertSame('STARTER', $bundle->sku);
        self::assertSame(['BOOK', 'DICE'], array_map(static fn (BundleComponent $component): string => $component->sku, $bundle->components));
        self::assertSame(2, $bundle->quantityOf('DICE'));
        self::assertSame(1, $bundle->quantityOf('BOOK'));
        self::assertSame(0, $bundle->quantityOf('MAP'));
        self::assertTrue(BundleKind::Assembled->taxedAsOne());
        self::assertFalse(BundleKind::Collection->taxedAsOne());
    }

    public function testABundleIsWrittenAndReadBack(): void
    {
        $bundle = self::starter();
        $row = $bundle->toArray();

        self::assertSame([
            'sku' => 'STARTER',
            'kind' => 'assembled',
            'currency' => 'CAD',
            'components' => [['sku' => 'BOOK', 'quantity' => 1], ['sku' => 'DICE', 'quantity' => 2]],
            'price' => ['fixed' => null, 'discount_ppm' => 150_000, 'rounding' => 'HalfAwayFromZero'],
        ], $row);
        self::assertSame($row, ProductBundle::fromArray($row)->toArray());

        $fixed = new ProductBundle('PDF-PRINT', BundleKind::Collection, Currency::of('CAD'), [new BundleComponent('PRINT'), new BundleComponent('PDF')], PricePolicy::fixed(Money::of(4500, Currency::of('CAD'))));
        self::assertSame(['fixed' => 4500, 'discount_ppm' => null, 'rounding' => 'HalfAwayFromZero'], $fixed->toArray()['price']);
        self::assertSame($fixed->toArray(), ProductBundle::fromArray($fixed->toArray())->toArray());
    }

    /**
     * @param list<BundleComponent> $components
     */
    #[DataProvider('refusedBundles')]
    public function testABundleOutOfShapeIsRefused(string $sku, array $components, PricePolicy $price, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ProductBundle($sku, BundleKind::Assembled, Currency::of('CAD'), $components, $price);
    }

    /**
     * @return iterable<string, array{string, list<BundleComponent>, PricePolicy, string}>
     */
    public static function refusedBundles(): iterable
    {
        $discount = PricePolicy::discountOnComponents(Rate::zero());

        yield 'no component' => ['B', [], $discount, 'The number of components is from 1 to 50, not 0.'];
        yield 'too many' => ['B', array_map(static fn (int $i): BundleComponent => new BundleComponent('C'.$i), range(1, 51)), $discount, 'The number of components is from 1 to 50, not 51.'];
        yield 'itself' => ['B', [new BundleComponent('B')], $discount, 'The bundle "B" is not one of its own components.'];
        yield 'twice' => ['B', [new BundleComponent('C'), new BundleComponent('C', 2)], $discount, 'The component "C" appears once in a bundle; raise its quantity instead.'];
        yield 'bad sku' => ['-B', [new BundleComponent('C')], $discount, 'The bundle SKU is up to 64 letters, digits, "_", ".", "/" or "-", starting with a letter or a digit.'];
        yield 'other currency' => ['B', [new BundleComponent('C')], PricePolicy::fixed(Money::of(100, Currency::of('USD'))), 'The fixed price of "B" is in the bundle\'s currency, CAD.'];
    }

    public function testFiftyComponentsAreAccepted(): void
    {
        $bundle = new ProductBundle('B', BundleKind::Assembled, Currency::of('CAD'), array_map(static fn (int $i): BundleComponent => new BundleComponent('C'.$i), range(1, 50)), PricePolicy::discountOnComponents(Rate::zero()));

        self::assertCount(50, $bundle->components);
    }

    #[DataProvider('refusedComponents')]
    public function testAComponentOutOfBoundsIsRefused(string $sku, int $quantity, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        new BundleComponent($sku, $quantity);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function refusedComponents(): iterable
    {
        yield 'zero' => ['C', 0, 'The quantity of a component is from 1 to 1000, not 0.'];
        yield 'too many' => ['C', 1001, 'The quantity of a component is from 1 to 1000, not 1001.'];
        yield 'empty sku' => ['', 1, 'The component SKU is up to 64 letters'];
        yield 'long sku' => [str_repeat('a', 65), 1, 'The component SKU is up to 64 letters'];
        yield 'trailing newline' => ["C\n", 1, 'The component SKU is up to 64 letters'];
    }

    public function testTheBoundsOfAComponentAreAccepted(): void
    {
        self::assertSame(1000, new BundleComponent(str_repeat('a', 64), 1000)->quantity);
        self::assertSame('a/b.c_d-e', new BundleComponent('a/b.c_d-e')->sku);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    #[DataProvider('refusedRows')]
    public function testAStoredRowOutOfShapeIsRefused(array $row, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        ProductBundle::fromArray($row);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedRows(): iterable
    {
        $row = self::starter()->toArray();

        yield 'kind' => [['kind' => 'kit'] + $row, 'The stored kind is "assembled" or "collection".'];
        yield 'kind type' => [['kind' => 1] + $row, 'The stored field "kind" is a string.'];
        yield 'currency' => [['currency' => 'XYZ'] + $row, 'The currency is a code of ISO 4217.'];
        yield 'components' => [['components' => ['a' => []]] + $row, 'The stored field "components" is a list of at most 50 entries.'];
        yield 'component' => [['components' => ['BOOK']] + $row, 'The stored component is an array.'];
        yield 'quantity' => [['components' => [['sku' => 'BOOK', 'quantity' => '1']]] + $row, 'The stored field "quantity" is an integer.'];
        yield 'price' => [['price' => null] + $row, 'The stored price policy is an array.'];
        yield 'both' => [['price' => ['fixed' => 100, 'discount_ppm' => 0, 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored price policy has a fixed price or a discount, one of the two.'];
        yield 'neither' => [['price' => ['fixed' => null, 'discount_ppm' => null, 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored price policy has a fixed price or a discount, one of the two.'];
        yield 'rounding' => [['price' => ['fixed' => null, 'discount_ppm' => 0, 'rounding' => 'Up']] + $row, 'The stored rounding mode names a case of \RoundingMode.'];
        yield 'fixed rounding' => [['price' => ['fixed' => 100, 'discount_ppm' => null, 'rounding' => 'NegativeInfinity']] + $row, 'The stored price policy rounds a fixed price half away from zero.'];
        yield 'fixed zero' => [['price' => ['fixed' => 0, 'discount_ppm' => null, 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored fixed price is from 1 to'];
        yield 'discount high' => [['price' => ['fixed' => null, 'discount_ppm' => 1_000_000, 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored discount is from 0 to 999999, not 1000000.'];
        yield 'discount low' => [['price' => ['fixed' => null, 'discount_ppm' => -1, 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored discount is from 0 to 999999, not -1.'];
        yield 'discount type' => [['price' => ['fixed' => null, 'discount_ppm' => '5', 'rounding' => 'HalfAwayFromZero']] + $row, 'The stored field "discount_ppm" is an integer.'];
    }

    public function testAPolicyKeepsItsRoundingAndRefusesARateOutOfRange(): void
    {
        $policy = PricePolicy::discountOnComponents(Rate::of(999_999), RoundingMode::NegativeInfinity);
        self::assertFalse($policy->isFixed());
        self::assertSame(['fixed' => null, 'discount_ppm' => 999_999, 'rounding' => 'NegativeInfinity'], $policy->toArray());
        self::assertSame($policy->toArray(), PricePolicy::fromArray($policy->toArray(), Currency::of('CAD'))->toArray());
        self::assertTrue(PricePolicy::fixed(Money::of(1, Currency::of('CAD')))->isFixed());

        foreach ([Rate::of(1_000_000), Rate::of(-1)] as $rate) {
            try {
                PricePolicy::discountOnComponents($rate);
                self::fail('The rate is refused.');
            } catch (InvalidBundle $refused) {
                self::assertSame('The discount is from 0 to 99.9999 %.', $refused->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidBundle::because('fixed price', 'is above zero'));
        PricePolicy::fixed(Money::zero(Currency::of('CAD')));
    }

    public function testAPolicyPricesFromTheComponentsValue(): void
    {
        $cad = Currency::of('CAD');

        self::assertSame(4250, PricePolicy::discountOnComponents(Rate::ofPercent('15'))->priceOf(Money::of(5000, $cad))->minorUnits);
        self::assertSame(4500, PricePolicy::fixed(Money::of(4500, $cad))->priceOf(Money::of(5000, $cad))->minorUnits);
        // 33.3333 % of 1.00 is a discount of 0.333333: 0.33 half away from zero, 0.34 towards positive infinity.
        self::assertSame(67, PricePolicy::discountOnComponents(Rate::of(333_333))->priceOf(Money::of(100, $cad))->minorUnits);
        self::assertSame(66, PricePolicy::discountOnComponents(Rate::of(333_333), RoundingMode::PositiveInfinity)->priceOf(Money::of(100, $cad))->minorUnits);
    }

    private static function starter(): ProductBundle
    {
        return new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('CAD'), [new BundleComponent('BOOK'), new BundleComponent('DICE', 2)], PricePolicy::discountOnComponents(Rate::ofPercent('15')));
    }
}
