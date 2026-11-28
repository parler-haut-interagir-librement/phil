<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleCost;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\BundleRefund;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Exception\ReturnRefused;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;
use Phil\ProductBundles\SoldComponent;
use Phil\ProductBundles\StockDraw;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoldBundle::class)]
#[CoversClass(SoldComponent::class)]
#[CoversClass(BundleRefund::class)]
#[CoversClass(StockDraw::class)]
#[CoversClass(ReturnRefused::class)]
final class SoldBundleTest extends TestCase
{
    public function testASaleFreezesThePriceTheSharesAndTheCosts(): void
    {
        $sold = $this->sold(3);

        self::assertSame('PRINT-PDF', $sold->sku);
        self::assertSame(BundleKind::Collection, $sold->kind);
        self::assertSame(4500, $sold->unitPrice->minorUnits);
        self::assertSame(13500, $sold->total()->minorUnits);
        self::assertSame(2400, $sold->unitCost()?->minorUnits);
        self::assertSame([['PRINT', 1, 3375, 2400], ['PDF', 1, 1125, 0]], array_map(
            static fn (SoldComponent $component): array => [$component->sku, $component->perBundle, $component->unitShare->minorUnits, $component->unitCost?->minorUnits],
            $sold->components,
        ));
        self::assertSame([10125, 3375], array_map(static fn (Money $share): int => $share->minorUnits, $sold->shares()));
        self::assertSame([['PRINT', 3], ['PDF', 3]], array_map(static fn (StockDraw $draw): array => [$draw->sku, $draw->units], $sold->draws()));
        self::assertSame(3, $sold->returnable());
    }

    public function testAnUnknownCostIsKeptUnknown(): void
    {
        $bundle = $this->bundle();
        $shop = new Shop(['PRINT' => 3000, 'PDF' => 1000]);
        $sold = SoldBundle::of($bundle, new BundlePricing($shop)->quote($bundle), 1, new ComposedCost($shop)->of($bundle));

        self::assertNull($sold->unitCost());
        self::assertNull(SoldBundle::of($bundle, new BundlePricing($shop)->quote($bundle), 1)->components[0]->unitCost);
    }

    public function testWholeBundlesAreReturnedUntilNoneIsLeft(): void
    {
        $refund = $this->sold(3)->refund(2);

        self::assertSame(2, $refund->bundles);
        self::assertSame(9000, $refund->amount->minorUnits);
        self::assertSame([6750, 2250], array_map(static fn (Money $share): int => $share->minorUnits, $refund->shares));
        self::assertSame([['PRINT', 2], ['PDF', 2]], array_map(static fn (StockDraw $draw): array => [$draw->sku, $draw->units], $refund->restock));
        self::assertSame(2, $refund->after->returned);
        self::assertSame(1, $refund->after->returnable());

        $last = $refund->after->refund(1);
        self::assertSame(0, $last->after->returnable());

        try {
            $last->after->refund(1);
            self::fail('Nothing is left to return.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('The return of the bundle "PRINT-PDF" is refused: 0 left to return, 1 asked.', $returnRefused->getMessage());
        }

        $this->expectExceptionObject(ReturnRefused::because('PRINT-PDF', 'one bundle or more is returned, whole'));
        $this->sold(1)->refund(0);
    }

    public function testFreeSharesFollowTheQuantities(): void
    {
        $sold = new SoldBundle('B', BundleKind::Assembled, Shop::cad(0), [new SoldComponent('A', 1, Shop::cad(0)), new SoldComponent('C', 3, Shop::cad(0))], 2);

        self::assertSame([0, 0], array_map(static fn (Money $share): int => $share->minorUnits, $sold->shares()));
        self::assertSame([['A', 2], ['C', 6]], array_map(static fn (StockDraw $draw): array => [$draw->sku, $draw->units], $sold->draws()));
        self::assertSame(0, $sold->refund(1)->amount->minorUnits);
    }

    public function testASoldBundleIsWrittenAndReadBack(): void
    {
        $sold = $this->sold(3)->refund(1)->after;
        $row = $sold->toArray();

        self::assertSame([
            'sku' => 'PRINT-PDF',
            'kind' => 'collection',
            'currency' => 'CAD',
            'unit_price' => 4500,
            'quantity' => 3,
            'returned' => 1,
            'components' => [
                ['sku' => 'PRINT', 'per_bundle' => 1, 'unit_share' => 3375, 'unit_cost' => 2400, 'cost_currency' => 'CAD'],
                ['sku' => 'PDF', 'per_bundle' => 1, 'unit_share' => 1125, 'unit_cost' => 0, 'cost_currency' => 'CAD'],
            ],
        ], $row);
        self::assertSame($row, SoldBundle::fromArray($row)->toArray());

        $row['components'][1]['unit_cost'] = null;
        $row['components'][1]['cost_currency'] = null;
        self::assertNull(SoldBundle::fromArray($row)->components[1]->unitCost);
    }

    /**
     * @param list<SoldComponent> $components
     */
    #[DataProvider('refusedLines')]
    public function testALineOutOfShapeIsRefused(Money $price, array $components, int $quantity, int $returned, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SoldBundle('B', BundleKind::Assembled, $price, $components, $quantity, $returned);
    }

    /**
     * @return iterable<string, array{Money, list<SoldComponent>, int, int, string}>
     */
    public static function refusedLines(): iterable
    {
        $one = [new SoldComponent('A', 1, Shop::cad(100))];

        yield 'no component' => [Shop::cad(100), [], 1, 0, 'The number of components is from 1 to 50, not 0.'];
        yield 'quantity zero' => [Shop::cad(100), $one, 0, 0, 'The quantity sold is from 1 to 1000, not 0.'];
        yield 'quantity high' => [Shop::cad(100), $one, 1001, 0, 'The quantity sold is from 1 to 1000, not 1001.'];
        yield 'returned high' => [Shop::cad(100), $one, 2, 3, 'The quantity returned is from 0 to 2, not 3.'];
        yield 'returned low' => [Shop::cad(100), $one, 2, -1, 'The quantity returned is from 0 to 2, not -1.'];
        yield 'negative price' => [Shop::cad(-100), [new SoldComponent('A', 1, Shop::cad(-100))], 1, 0, 'The unit price of "B" is zero or more.'];
        yield 'bad component sku' => [Shop::cad(100), [new SoldComponent('', 1, Shop::cad(100))], 1, 0, 'The component SKU is up to 64'];
        yield 'per bundle zero' => [Shop::cad(100), [new SoldComponent('A', 0, Shop::cad(100))], 1, 0, 'The quantity of a component is from 1 to 1000, not 0.'];
        yield 'twice' => [Shop::cad(100), [new SoldComponent('A', 1, Shop::cad(50)), new SoldComponent('A', 1, Shop::cad(50))], 1, 0, 'The component "A" appears once in a bundle, never the bundle itself.'];
        yield 'itself' => [Shop::cad(100), [new SoldComponent('B', 1, Shop::cad(100))], 1, 0, 'The component "B" appears once in a bundle, never the bundle itself.'];
        yield 'share currency' => [Shop::cad(100), [new SoldComponent('A', 1, Money::of(100, Currency::of('USD')))], 1, 0, 'The share of "A" is zero or more, in the price\'s currency.'];
        yield 'negative share' => [Shop::cad(100), [new SoldComponent('A', 1, Shop::cad(101)), new SoldComponent('C', 1, Shop::cad(-1))], 1, 0, 'The share of "C" is zero or more, in the price\'s currency.'];
        yield 'shares sum' => [Shop::cad(100), [new SoldComponent('A', 1, Shop::cad(99))], 1, 0, 'The shares of "B" add up to its unit price.'];
    }

    /**
     * @param array<array-key, mixed> $change
     */
    #[DataProvider('refusedRows')]
    public function testAStoredLineOutOfShapeIsRefused(array $change, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        SoldBundle::fromArray($change + $this->sold(1)->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedRows(): iterable
    {
        yield 'kind' => [['kind' => 'kit'], 'The stored kind is "assembled" or "collection".'];
        yield 'currency' => [['currency' => 'cad'], 'The currency is a code of ISO 4217.'];
        yield 'price' => [['unit_price' => \PHP_INT_MIN], 'The stored field "unit_price" is an amount.'];
        yield 'components' => [['components' => 'none'], 'The stored field "components" is a list of at most 50 entries.'];
        yield 'cost half' => [['components' => [['sku' => 'A', 'per_bundle' => 1, 'unit_share' => 4500, 'unit_cost' => 1, 'cost_currency' => null]]], 'The stored unit cost has an amount and a currency, or neither.'];
        yield 'currency half' => [['components' => [['sku' => 'A', 'per_bundle' => 1, 'unit_share' => 4500, 'unit_cost' => null, 'cost_currency' => 'CAD']]], 'The stored unit cost has an amount and a currency, or neither.'];
        yield 'cost currency' => [['components' => [['sku' => 'A', 'per_bundle' => 1, 'unit_share' => 4500, 'unit_cost' => 1, 'cost_currency' => 7]]], 'The stored field "cost_currency" is a string.'];
        yield 'returned' => [['returned' => null], 'The stored field "returned" is an integer.'];
    }

    private function bundle(): ProductBundle
    {
        return new ProductBundle('PRINT-PDF', BundleKind::Collection, Currency::of('CAD'), [new BundleComponent('PRINT'), new BundleComponent('PDF')], PricePolicy::fixed(Shop::cad(4500)));
    }

    private function sold(int $quantity): SoldBundle
    {
        $bundle = $this->bundle();
        $shop = new Shop(['PRINT' => 3000, 'PDF' => 1000], costs: ['PRINT' => 2400, 'PDF' => 0]);

        return SoldBundle::of($bundle, new BundlePricing($shop)->quote($bundle), $quantity, new ComposedCost($shop)->of($bundle));
    }

    public function testTheSkuOfALineIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBundle::because('bundle SKU', 'is up to 64 letters, digits, "_", ".", "/" or "-", starting with a letter or a digit'));

        new SoldBundle('bad sku', BundleKind::Assembled, Shop::cad(100), [new SoldComponent('A', 1, Shop::cad(100))], 1);
    }

    public function testAnUnknownCostIsWrittenAsNulls(): void
    {
        $line = new SoldBundle('B', BundleKind::Assembled, Shop::cad(100), [new SoldComponent('A', 1, Shop::cad(100))], 1)->toArray()['components'][0];

        self::assertNull($line['unit_cost']);
        self::assertNull($line['cost_currency']);
    }

    public function testACostInTheLineKeepsItsOwnCurrency(): void
    {
        $sold = new SoldBundle('B', BundleKind::Assembled, Shop::cad(100), [new SoldComponent('A', 2, Shop::cad(100), Money::of(30, Currency::of('USD')))], 1);

        self::assertSame(60, $sold->unitCost()?->minorUnits);
        self::assertSame('USD', $sold->toArray()['components'][0]['cost_currency']);
        self::assertSame('USD', SoldBundle::fromArray($sold->toArray())->components[0]->unitCost?->currency->code);
        self::assertNull(new BundleCost('B', null, [], [])->unitCost);
    }
}
