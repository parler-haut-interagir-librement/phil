<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Domain;

use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\CommerceReportsBundle\Domain\Formula\SellThrough;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SellThrough::class)]
#[CoversClass(InvalidFigure::class)]
final class SellThroughTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, int|null}>
     */
    public static function figures(): iterable
    {
        yield 'a third, rounded down' => [1, 3, 0, 333_333];
        yield 'two thirds, rounded up' => [2, 3, 0, 666_667];
        yield 'an exact eighth' => [1, 8, 0, 125_000];
        yield 'half a part, rounded up' => [1, 2_000_000, 0, 1];
        yield 'just under half a part, rounded down' => [1, 2_000_001, 0, 0];
        yield 'a quarter of a part, rounded down' => [1, 4_000_000, 0, 0];
        yield 'three quarters of a part, rounded up' => [3, 4_000_000, 0, 1];
        yield 'one and a half parts, rounded up' => [3, 2_000_000, 0, 2];
        yield 'the units received count' => [1, 0, 4, 250_000];
        yield 'the start and the units received add up' => [1, 2, 2, 250_000];
        yield 'everything sold from one unit' => [1, 1, 0, 1_000_000];
        yield 'nothing sold' => [0, 5, 0, 0];
        yield 'more sold than stocked' => [3, 2, 0, 1_500_000];
        yield 'nothing to sell from' => [5, 0, 0, null];
        yield 'nothing sold from nothing' => [0, 0, 0, null];
        yield 'a negative stock' => [1, -3, 2, null];
        yield 'the largest counts' => [SellThrough::MAX_UNITS, SellThrough::MAX_UNITS, 0, 1_000_000];
        yield 'the largest stock, from both sides' => [1, SellThrough::MAX_UNITS - 1, 1, 0];
    }

    #[DataProvider('figures')]
    public function testThePartsPerMillion(int $sold, int $start, int $received, ?int $ppm): void
    {
        self::assertSame($ppm, SellThrough::ppm($sold, $start, $received));
    }

    public function testNegativeUnitsSoldAreRefused(): void
    {
        $this->expectException(InvalidFigure::class);
        $this->expectExceptionMessageIsOrContains('the units sold are not negative');

        SellThrough::ppm(-1, 5, 0);
    }

    public function testTooManyUnitsSoldAreRefused(): void
    {
        $this->expectException(InvalidFigure::class);
        $this->expectExceptionMessageIsOrContains('a sell-through counts at most 4000000000000 units');

        SellThrough::ppm(SellThrough::MAX_UNITS + 1, 5, 0);
    }

    public function testTooLargeAStockIsRefused(): void
    {
        $this->expectException(InvalidFigure::class);

        SellThrough::ppm(1, SellThrough::MAX_UNITS, 1);
    }
}
