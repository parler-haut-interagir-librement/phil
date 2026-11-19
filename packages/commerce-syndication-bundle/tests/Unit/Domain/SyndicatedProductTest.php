<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyndicatedProduct::class)]
#[CoversClass(FeedItem::class)]
final class SyndicatedProductTest extends TestCase
{
    public function testAProductIsWhatItsSupplierLastDescribed(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $product = SyndicatedProduct::from(Partners::supplier(), $this->item(), $at);

        self::assertSame(['supplier', 'TEA-1', 'Green tea', 1_000, 700, 'CAD', 'food', 5, $at, true], [
            $product->supplier, $product->sku, $product->name(), $product->retail->minorUnits, $product->cost->minorUnits, $product->cost->currency->code,
            $product->taxCategory(), $product->available(), $product->describedAt(), $product->isSold(),
        ]);
    }

    public function testANewerDescriptionReplacesTheOldOne(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $product = SyndicatedProduct::from(Partners::supplier(), $this->item(), $at);
        $later = $at->modify('+1 microsecond');

        self::assertTrue($product->describe(FeedItem::of('TEA-1', 'Black tea', Money::of(2_000, Currency::of('USD')), Money::of(1_500, Currency::of('USD')), 'standard', 0, false), $later));

        self::assertSame(['Black tea', 2_000, 1_500, 'USD', 'USD', 'standard', 0, $later, false], [
            $product->name(), $product->retail->minorUnits, $product->cost->minorUnits, $product->retail->currency->code, $product->cost->currency->code,
            $product->taxCategory(), $product->available(), $product->describedAt(), $product->isSold(),
        ]);
    }

    public function testAnOlderOrSimultaneousDescriptionChangesNothing(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $product = SyndicatedProduct::from(Partners::supplier(), $this->item(), $at);
        $other = FeedItem::of('TEA-1', 'Black tea', Money::of(2_000, Currency::of('CAD')), Money::of(1_500, Currency::of('CAD')), 'standard', 0);

        self::assertFalse($product->describe($other, $at));
        self::assertFalse($product->describe($other, $at->modify('-1 second')));
        self::assertSame(['Green tea', 1_000, $at], [$product->name(), $product->retail->minorUnits, $product->describedAt()]);
    }

    public function testAProductIsSoldWhileActiveAndInStock(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $cad = Currency::of('CAD');

        self::assertTrue(SyndicatedProduct::from(Partners::supplier(), FeedItem::of('A', 'A', Money::of(1, $cad), Money::of(1, $cad), 'food', 1), $at)->isSold());
        self::assertFalse(SyndicatedProduct::from(Partners::supplier(), FeedItem::of('A', 'A', Money::of(1, $cad), Money::of(1, $cad), 'food', 0), $at)->isSold());
        self::assertFalse(SyndicatedProduct::from(Partners::supplier(), FeedItem::of('A', 'A', Money::of(1, $cad), Money::of(1, $cad), 'food', 1, false), $at)->isSold());
    }

    public function testAnItemCarriesItsDescription(): void
    {
        $item = FeedItem::of(str_repeat('A', 64), str_repeat('é', 255), Money::of(500, Currency::of('CAD')), Money::of(500, Currency::of('CAD')), str_repeat('t', 64), 0);

        self::assertSame([str_repeat('A', 64), str_repeat('é', 255), 500, 500, str_repeat('t', 64), 0, true], [
            $item->sku, $item->name, $item->retail->minorUnits, $item->cost->minorUnits, $item->taxCategory, $item->available, $item->active,
        ]);
        self::assertSame(0, FeedItem::of('A', 'A', Money::of(0, Currency::of('CAD')), Money::of(0, Currency::of('CAD')), 'food', 0)->cost->minorUnits);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badSkus(): iterable
    {
        yield 'empty' => ['', '"" is not a SKU: 1 to 64 visible ASCII characters.'];
        yield 'too long' => [str_repeat('A', 65), \sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', str_repeat('A', 65))];
        yield 'a trailing newline' => ["A\n", "\"A\n\" is not a SKU: 1 to 64 visible ASCII characters."];
    }

    #[DataProvider('badSkus')]
    public function testAnItemHasASku(string $sku, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        FeedItem::of($sku, 'Tea', Money::of(1, Currency::of('CAD')), Money::of(1, Currency::of('CAD')), 'food', 1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badDescriptions(): iterable
    {
        yield 'a blank name' => [' ', 'food'];
        yield 'a long name' => [str_repeat('é', 256), 'food'];
        yield 'no tax category' => ['Tea', ''];
        yield 'a long tax category' => ['Tea', str_repeat('t', 65)];
    }

    #[DataProvider('badDescriptions')]
    public function testAnItemHasANameAndATaxCategory(string $name, string $taxCategory): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product TEA has a name of 1 to 255 characters and a tax category.');

        FeedItem::of('TEA', $name, Money::of(1, Currency::of('CAD')), Money::of(1, Currency::of('CAD')), $taxCategory, 1);
    }

    /**
     * @return iterable<string, array{Money, Money}>
     */
    public static function badCosts(): iterable
    {
        yield 'another currency' => [Money::of(10, Currency::of('CAD')), Money::of(5, Currency::of('USD'))];
        yield 'a negative cost' => [Money::of(10, Currency::of('CAD')), Money::of(-1, Currency::of('CAD'))];
        yield 'a cost above the price' => [Money::of(10, Currency::of('CAD')), Money::of(11, Currency::of('CAD'))];
    }

    #[DataProvider('badCosts')]
    public function testACostIsAtMostTheRetailPrice(Money $retail, Money $cost): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product TEA costs zero or more, at most its retail price, in its currency.');

        FeedItem::of('TEA', 'Tea', $retail, $cost, 'food', 1);
    }

    public function testAnItemHasZeroUnitsOrMore(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product TEA has zero units available or more.');

        FeedItem::of('TEA', 'Tea', Money::of(1, Currency::of('CAD')), Money::of(1, Currency::of('CAD')), 'food', -1);
    }

    private function item(): FeedItem
    {
        return FeedItem::of('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), Money::of(700, Currency::of('CAD')), 'food', 5);
    }
}
