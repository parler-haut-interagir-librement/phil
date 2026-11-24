<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\CommerceReportsBundle\Domain\ValueObject\Text;
use Phil\CommerceReportsBundle\Domain\ValueObject\Units;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoldLine::class)]
#[CoversClass(ReturnedLine::class)]
#[CoversClass(StockFigure::class)]
#[CoversClass(StockedSku::class)]
#[CoversClass(Text::class)]
#[CoversClass(Units::class)]
final class LinesTest extends TestCase
{
    public function testASoldLineKeepsItsFigures(): void
    {
        $line = Lines::sold(quantity: Units::MAX, gross: 0, discounts: 0, category: null, channel: null, location: null, cashier: null, product: str_repeat('é', Text::MAX));

        self::assertSame(Units::MAX, $line->quantity);
        self::assertNull($line->category);
        self::assertSame(str_repeat('é', Text::MAX), $line->product);
        self::assertTrue($line->gross->isZero());
    }

    public function testAReturnedLineKeepsItsFigures(): void
    {
        $line = Lines::returned(quantity: 1, amount: 0, category: null);

        self::assertSame(1, $line->quantity);
        self::assertNull($line->category);
        self::assertTrue($line->amount->isZero());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an empty SKU' => [static fn (): SoldLine => Lines::sold(sku: ''), 'a SKU is not empty'];
        yield 'a blank SKU' => [static fn (): SoldLine => Lines::sold(sku: '  '), 'a SKU is not empty'];
        yield 'a long product' => [static fn (): SoldLine => Lines::sold(product: str_repeat('é', 201)), 'a product is at most 200 characters long'];
        yield 'an empty category' => [static fn (): SoldLine => Lines::sold(category: ''), 'a category is not empty'];
        yield 'an empty channel' => [static fn (): SoldLine => Lines::sold(channel: ''), 'a channel is not empty'];
        yield 'an empty location' => [static fn (): SoldLine => Lines::sold(location: ''), 'a location is not empty'];
        yield 'an empty cashier' => [static fn (): SoldLine => Lines::sold(cashier: ''), 'a cashier is not empty'];
        yield 'no unit' => [static fn (): SoldLine => Lines::sold(quantity: 0), 'a quantity is from 1 to 1000000000 units'];
        yield 'too many units' => [static fn (): SoldLine => Lines::sold(quantity: Units::MAX + 1), 'a quantity is from 1 to 1000000000 units'];
        yield 'a negative gross amount' => [static fn (): SoldLine => Lines::sold(gross: -1), 'the gross amount and the discounts of the SKU "BOOK-1" are not negative'];
        yield 'negative discounts' => [static fn (): SoldLine => Lines::sold(discounts: -1), 'are not negative'];
        yield 'discounts in another currency' => [static fn (): SoldLine => new SoldLine('A', 'A', null, null, null, null, 1, Lines::money(100), Lines::money(0, 'USD'), new DateTimeImmutable()), 'are in one currency'];
        yield 'a returned line without its SKU' => [static fn (): ReturnedLine => Lines::returned(sku: ''), 'a SKU is not empty'];
        yield 'a returned line with an empty category' => [static fn (): ReturnedLine => Lines::returned(category: ''), 'a category is not empty'];
        yield 'a returned line with an empty channel' => [static fn (): ReturnedLine => Lines::returned(channel: ''), 'a channel is not empty'];
        yield 'a returned line with an empty location' => [static fn (): ReturnedLine => Lines::returned(location: ''), 'a location is not empty'];
        yield 'a returned line with an empty cashier' => [static fn (): ReturnedLine => Lines::returned(cashier: ''), 'a cashier is not empty'];
        yield 'a returned line without a unit' => [static fn (): ReturnedLine => Lines::returned(quantity: 0), 'a quantity is from 1'];
        yield 'a negative amount returned' => [static fn (): ReturnedLine => Lines::returned(amount: -1), 'the amount returned for the SKU "BOOK-1" is not negative'];
        yield 'a returned line without its product' => [static fn (): ReturnedLine => new ReturnedLine('A', '', null, null, null, null, 1, Lines::money(1), new DateTimeImmutable()), 'a product is not empty'];
        yield 'a figure without its SKU' => [static fn (): StockFigure => new StockFigure('', 0, 0, 0, null), 'a SKU is not empty'];
        yield 'a starting stock too low' => [static fn (): StockFigure => new StockFigure('A', -Units::MAX - 1, 0, 0, null), 'a starting stock is from -1000000000 to 1000000000 units'];
        yield 'a starting stock too high' => [static fn (): StockFigure => new StockFigure('A', Units::MAX + 1, 0, 0, null), 'a starting stock is from'];
        yield 'a stock available too low' => [static fn (): StockFigure => new StockFigure('A', 0, 0, -Units::MAX - 1, null), 'a stock available is from'];
        yield 'a stock available too high' => [static fn (): StockFigure => new StockFigure('A', 0, 0, Units::MAX + 1, null), 'a stock available is from'];
        yield 'negative units received' => [static fn (): StockFigure => new StockFigure('A', 0, -1, 0, null), 'the units received of the SKU "A" are from 0 to 1000000000'];
        yield 'too many units received' => [static fn (): StockFigure => new StockFigure('A', 0, Units::MAX + 1, 0, null), 'the units received of the SKU "A" are from 0'];
        yield 'a stocked SKU without its SKU' => [static fn (): StockedSku => new StockedSku('', 'A'), 'a SKU is not empty'];
        yield 'a stocked SKU without its product' => [static fn (): StockedSku => new StockedSku('A', ''), 'a product is not empty'];
        yield 'a stocked SKU with an empty category' => [static fn (): StockedSku => new StockedSku('A', 'A', ''), 'a category is not empty'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalid')]
    public function testFiguresOutOfBoundsAreRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidFigure::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAStockFigureTakesItsBounds(): void
    {
        $low = new StockFigure('A', -Units::MAX, 0, -Units::MAX, null);
        $high = new StockFigure('A', Units::MAX, Units::MAX, Units::MAX, new DateTimeImmutable('2026-11-01'));

        self::assertSame(-Units::MAX, $low->start);
        self::assertSame(-Units::MAX, $low->available);
        self::assertSame(0, $low->received);
        self::assertSame(Units::MAX, $high->received);
        self::assertNotNull($high->lastSoldAt);
    }

    public function testAStockedSkuHasNoCategoryByDefault(): void
    {
        self::assertNull(new StockedSku('A', 'Apple')->category);
        self::assertSame('fruit', new StockedSku('A', 'Apple', 'fruit')->category);
    }
}
