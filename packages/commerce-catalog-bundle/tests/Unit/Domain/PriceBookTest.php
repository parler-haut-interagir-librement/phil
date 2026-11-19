<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Domain\ValueObject\PriceTier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceBook::class)]
#[CoversClass(PriceTier::class)]
final class PriceBookTest extends TestCase
{
    public function testABookHoldsItsConditions(): void
    {
        $starts = new DateTimeImmutable('2026-11-27T00:00:00Z');
        $ends = new DateTimeImmutable('2026-12-01T00:00:00Z');
        $book = PriceBook::create('black-friday', 'Black Friday', Currency::of('CAD'), 'wholesale', 'store', $starts, $ends, ['vip', 'staff', 'vip']);

        self::assertSame('black-friday', $book->code);
        self::assertSame('Black Friday', $book->name);
        self::assertSame(['vip', 'staff'], $book->after());
        self::assertSame('CAD', $book->currency->code);
        self::assertSame('wholesale', $book->customerGroup);
        self::assertSame('store', $book->channel);
        self::assertSame($starts, $book->startsAt);
        self::assertSame($ends, $book->endsAt);
        self::assertTrue($book->isActive());
        self::assertSame([], $book->tiers());
    }

    /**
     * @return iterable<string, array{?string, ?string, string, bool}>
     */
    public static function applications(): iterable
    {
        yield 'the group, the channel, within the period' => ['wholesale', 'store', '2026-11-28T12:00:00Z', true];
        yield 'at the start' => ['wholesale', 'store', '2026-11-27T00:00:00Z', true];
        yield 'at the end, excluded' => ['wholesale', 'store', '2026-12-01T00:00:00Z', false];
        yield 'before' => ['wholesale', 'store', '2026-11-26T23:59:59Z', false];
        yield 'another group' => ['retail', 'store', '2026-11-28T12:00:00Z', false];
        yield 'no group' => [null, 'store', '2026-11-28T12:00:00Z', false];
        yield 'another channel' => ['wholesale', 'web', '2026-11-28T12:00:00Z', false];
        yield 'no channel' => ['wholesale', null, '2026-11-28T12:00:00Z', false];
    }

    #[DataProvider('applications')]
    public function testABookAppliesOnlyWhereAndWhenItSays(?string $group, ?string $channel, string $at, bool $applies): void
    {
        $book = PriceBook::create('bf', 'BF', Currency::of('CAD'), 'wholesale', 'store', new DateTimeImmutable('2026-11-27T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));

        self::assertSame($applies, $book->appliesTo($group, $channel, new DateTimeImmutable($at)));
    }

    public function testABookWithoutConditionsAppliesToEveryoneUntilWithdrawn(): void
    {
        $book = PriceBook::create('everyone', 'Everyone', Currency::of('CAD'));

        self::assertSame([], $book->after());
        self::assertTrue($book->appliesTo(null, null, new DateTimeImmutable('2000-01-01')));
        self::assertTrue($book->appliesTo('anyone', 'anywhere', new DateTimeImmutable('2100-01-01')));

        $book->withdraw();
        self::assertFalse($book->isActive());
        self::assertFalse($book->appliesTo(null, null, new DateTimeImmutable()));

        $book->restore();
        self::assertTrue($book->appliesTo(null, null, new DateTimeImmutable()));
    }

    public function testAnOpenEndedPeriodApplies(): void
    {
        $since = PriceBook::create('since', 'Since', Currency::of('CAD'), startsAt: new DateTimeImmutable('2026-01-01'));
        $until = PriceBook::create('until', 'Until', Currency::of('CAD'), endsAt: new DateTimeImmutable('2026-01-01'));

        self::assertTrue($since->appliesTo(null, null, new DateTimeImmutable('2030-01-01')));
        self::assertFalse($since->appliesTo(null, null, new DateTimeImmutable('2025-12-31')));
        self::assertTrue($until->appliesTo(null, null, new DateTimeImmutable('2025-12-31')));
        self::assertFalse($until->appliesTo(null, null, new DateTimeImmutable('2026-01-01')));
    }

    /**
     * The tier of the highest quantity reached wins; a SKU the book does not price for the quantity has no price.
     */
    public function testQuantityTiersPriceTheLine(): void
    {
        $book = $this->book();

        self::assertNull($book->priceFor('DICE', 1));
        self::assertSame(900, $book->priceFor('DICE', 5)?->minorUnits);
        self::assertSame(900, $book->priceFor('DICE', 9)?->minorUnits);
        self::assertSame(800, $book->priceFor('DICE', 10)?->minorUnits);
        self::assertSame(800, $book->priceFor('DICE', 500)?->minorUnits);
        $pdf = $book->priceFor('PDF', 1);
        self::assertNotNull($pdf);
        self::assertSame(100, $pdf->minorUnits);
        self::assertSame('CAD', $pdf->currency->code);
        self::assertNull($book->priceFor('MEEPLE', 100));
    }

    public function testTiersAreListedBySkuThenQuantity(): void
    {
        $tiers = $this->book()->tiers();

        self::assertSame([['DICE', 5, 900], ['DICE', 10, 800], ['PDF', 1, 100]], array_map(static fn (PriceTier $tier): array => [$tier->sku, $tier->minQuantity, $tier->unitPrice->minorUnits], $tiers));
    }

    /**
     * Tiers set in any order come out sorted, SKUs in byte order rather than as numbers, and setting an existing SKU
     * and quantity anywhere in the list replaces it in place.
     */
    public function testTiersSetInAnyOrderStaySorted(): void
    {
        $book = PriceBook::create('import', 'Import', Currency::of('CAD'));
        $set = [['M', 1], ['C', 5], ['X', 2], ['C', 1], ['A', 3], ['10', 1], ['9', 1], ['M', 10], ['B', 1], ['C', 3], ['Z', 1], ['M', 5], ['A', 1], ['Y', 1]];

        foreach ($set as $index => [$sku, $minQuantity]) {
            $book->setPrice(PriceTier::of($sku, $minQuantity, Money::of($index, Currency::of('CAD'))));
        }

        foreach ($set as [$sku, $minQuantity]) {
            $book->setPrice(PriceTier::of($sku, $minQuantity, Money::of(1_000 + $minQuantity, Currency::of('CAD'))));
        }

        $book->setPrice(PriceTier::of('C', 3, Money::of(42, Currency::of('CAD'))));

        self::assertSame(
            [['10', 1, 1_001], ['9', 1, 1_001], ['A', 1, 1_001], ['A', 3, 1_003], ['B', 1, 1_001], ['C', 1, 1_001], ['C', 3, 42], ['C', 5, 1_005], ['M', 1, 1_001], ['M', 5, 1_005], ['M', 10, 1_010], ['X', 2, 1_002], ['Y', 1, 1_001], ['Z', 1, 1_001]],
            array_map(static fn (PriceTier $tier): array => [$tier->sku, $tier->minQuantity, $tier->unitPrice->minorUnits], $book->tiers()),
        );
        self::assertSame(1_005, $book->priceFor('M', 9)?->minorUnits);
        self::assertSame(42, $book->priceFor('C', 4)?->minorUnits);
    }

    public function testATierIsReplacedAndRemoved(): void
    {
        $book = $this->book();
        $book->setPrice(PriceTier::of('DICE', 10, Money::of(750, Currency::of('CAD'))));

        self::assertSame(750, $book->priceFor('DICE', 10)?->minorUnits);
        self::assertCount(3, $book->tiers());

        $book->removePrice('DICE', 10);
        self::assertSame(900, $book->priceFor('DICE', 10)?->minorUnits);

        $book->removePrice('DICE', 7);
        self::assertCount(2, $book->tiers());
    }

    public function testAPriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('The price book wholesale counts in CAD; EUR is refused.');

        $this->book()->setPrice(PriceTier::of('DICE', 1, Money::of(1, Currency::of('EUR'))));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidBooks(): iterable
    {
        yield 'empty code' => [['code' => ''], '"" is not a price book code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'uppercase code' => [['code' => 'VIP'], 'is not a price book code'];
        yield 'code too long' => [['code' => str_repeat('a', 65)], 'is not a price book code'];
        yield 'code with a trailing newline' => [['code' => "vip\n"], 'is not a price book code'];
        yield 'blank name' => [['name' => ' '], 'The price book vip has a name.'];
        yield 'empty group' => [['group' => ''], 'A customer group or a channel of the price book vip, when given, has a name.'];
        yield 'empty channel' => [['channel' => ''], 'A customer group or a channel of the price book vip, when given, has a name.'];
        yield 'ending at its start' => [['ends' => '2026-01-01'], 'The price book vip ends after it starts.'];
        yield 'ending before its start' => [['ends' => '2025-12-31'], 'The price book vip ends after it starts.'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidBooks')]
    public function testAnInvalidBookIsRefused(array $override, string $message): void
    {
        /** @var array{code: string, name: string, group: ?string, channel: ?string, ends: ?string} $values */
        $values = [...['code' => 'vip', 'name' => 'VIP', 'group' => null, 'channel' => null, 'ends' => null], ...$override];

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PriceBook::create($values['code'], $values['name'], Currency::of('CAD'), $values['group'], $values['channel'], new DateTimeImmutable('2026-01-01'), null === $values['ends'] ? null : new DateTimeImmutable($values['ends']));
    }

    public function testABookIsPlacedAfterOthers(): void
    {
        $book = PriceBook::create('retail', 'Retail', Currency::of('CAD'));
        $book->placeAfter(['wholesale', 'vip', 'wholesale']);

        self::assertSame(['wholesale', 'vip'], $book->after());

        $book->placeAfter(['a', 'a', 'b']);
        self::assertSame(['a', 'b'], $book->after());

        $book->placeAfter(array_map(static fn (int $index): string => 'b'.$index, range(1, PriceBook::MAX_AFTER)));
        self::assertCount(PriceBook::MAX_AFTER, $book->after());

        $book->placeAfter([]);
        self::assertSame([], $book->after());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidAfterLists(): iterable
    {
        yield 'itself' => [['vip', 'retail'], 'The price book retail is never placed after itself.'];
        yield 'not a code' => [['VIP'], '"VIP" is not a price book code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'too many' => [array_map(static fn (int $index): string => 'b'.$index, range(0, PriceBook::MAX_AFTER)), 'The price book retail is placed after 1000 books at most.'];
    }

    /**
     * @param list<string> $after
     */
    #[DataProvider('invalidAfterLists')]
    public function testAnInvalidAfterListIsRefused(array $after, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PriceBook::create('retail', 'Retail', Currency::of('CAD'), after: $after);
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function invalidTiers(): iterable
    {
        yield 'no SKU' => ['', 1, 1, 'A price names its SKU.'];
        yield 'zero units' => ['DICE', 0, 1, 'A price applies from 1 unit at least; 0 is not.'];
        yield 'negative price' => ['DICE', 1, -1, 'The price of DICE is negative.'];
    }

    #[DataProvider('invalidTiers')]
    public function testAnInvalidTierIsRefused(string $sku, int $minQuantity, int $price, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PriceTier::of($sku, $minQuantity, Money::of($price, Currency::of('CAD')));
    }

    private function book(): PriceBook
    {
        $book = PriceBook::create('wholesale', 'Wholesale', Currency::of('CAD'));
        $book->setPrice(PriceTier::of('PDF', 1, Money::of(100, Currency::of('CAD'))));
        $book->setPrice(PriceTier::of('DICE', 10, Money::of(800, Currency::of('CAD'))));
        $book->setPrice(PriceTier::of('DICE', 5, Money::of(900, Currency::of('CAD'))));

        return $book;
    }
}
