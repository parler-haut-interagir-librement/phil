<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Infrastructure;

use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockFigures;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemorySoldLines::class)]
#[CoversClass(InMemoryReturnedLines::class)]
#[CoversClass(InMemoryStockFigures::class)]
#[CoversClass(InMemoryStockedSkus::class)]
final class InMemoryAdaptersTest extends TestCase
{
    public function testTheLinesSoldInsideThePeriod(): void
    {
        $lines = new InMemorySoldLines([
            Lines::sold('BEFORE', at: '2026-10-31T23:59:59Z'),
            Lines::sold('START', at: '2026-11-01T00:00:00Z'),
            Lines::sold('LAST', at: '2026-11-30T23:59:59Z'),
            Lines::sold('END', at: '2026-12-01T00:00:00Z'),
        ]);

        self::assertSame(['START', 'LAST'], array_map(static fn (SoldLine $line): string => $line->sku, iterator_to_array($lines->between(Lines::period()), false)));
        self::assertSame([], iterator_to_array(new InMemorySoldLines()->between(Lines::period()), false));
    }

    public function testTheLinesReturnedInsideThePeriod(): void
    {
        $lines = new InMemoryReturnedLines([
            Lines::returned('BEFORE', at: '2026-10-31T23:59:59Z'),
            Lines::returned('START', at: '2026-11-01T00:00:00Z'),
            Lines::returned('END', at: '2026-12-01T00:00:00Z'),
        ]);

        self::assertSame(['START'], array_map(static fn (ReturnedLine $line): string => $line->sku, iterator_to_array($lines->between(Lines::period()), false)));
        self::assertSame([], iterator_to_array(new InMemoryReturnedLines()->between(Lines::period()), false));
    }

    public function testTheStockFiguresBySku(): void
    {
        $known = new StockFigure('A', 4, 2, 3, null);
        $figures = new InMemoryStockFigures([new StockFigure('A', 1, 1, 1, null), $known]);

        self::assertSame($known, $figures->of('A', Lines::period()));
        self::assertEquals(new StockFigure('B', 0, 0, 0, null), $figures->of('B', Lines::period()));
        self::assertEquals(new StockFigure('B', 0, 0, 0, null), new InMemoryStockFigures()->of('B', Lines::period()));
    }

    public function testTheStockedSkus(): void
    {
        $last = new StockedSku('A', 'Apple', 'fruit');
        $skus = new InMemoryStockedSkus([new StockedSku('A', 'Old apple'), new StockedSku('B', 'Banana'), $last]);

        self::assertSame(['A', 'B'], array_map(static fn (StockedSku $sku): string => $sku->sku, iterator_to_array($skus->all(), false)));
        self::assertSame($last, $skus->find('A'));
        self::assertNull($skus->find('C'));
        self::assertSame([], iterator_to_array(new InMemoryStockedSkus()->all(), false));
    }
}
