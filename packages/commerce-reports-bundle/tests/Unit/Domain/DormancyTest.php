<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\CommerceReportsBundle\Domain\Formula\Dormancy;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dormancy::class)]
final class DormancyTest extends TestCase
{
    private const string NOW = '2026-11-20T12:00:00Z';

    /**
     * @return iterable<string, array{int, string|null, int, bool}>
     */
    public static function figures(): iterable
    {
        yield 'never sold, in stock' => [1, null, 90, true];
        yield 'never sold, out of stock' => [0, null, 90, false];
        yield 'never sold, below zero' => [-1, null, 90, false];
        yield 'sold exactly 90 days ago' => [3, '2026-08-22T12:00:00Z', 90, true];
        yield 'sold a second less than 90 days ago' => [3, '2026-08-22T12:00:01Z', 90, false];
        yield 'sold long ago' => [3, '2025-01-01T00:00:00Z', 90, true];
        yield 'sold long ago, out of stock' => [0, '2025-01-01T00:00:00Z', 90, false];
        yield 'sold yesterday, one day' => [3, '2026-11-19T12:00:00Z', 1, true];
        yield 'sold today, one day' => [3, '2026-11-20T00:00:00Z', 1, false];
        yield 'sold ten years ago, the longest threshold' => [3, '2016-11-22T12:00:00Z', 3650, true];
    }

    #[DataProvider('figures')]
    public function testWhetherStockIsDead(int $available, ?string $lastSoldAt, int $days, bool $dormant): void
    {
        $figure = new StockFigure('SKU', 0, 0, $available, null === $lastSoldAt ? null : new DateTimeImmutable($lastSoldAt));

        self::assertSame($dormant, Dormancy::isDormant($figure, new DateTimeImmutable(self::NOW), $days));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function thresholds(): iterable
    {
        yield 'no day' => [0];
        yield 'more than ten years' => [3651];
    }

    #[DataProvider('thresholds')]
    public function testTheDaysAreBounded(int $days): void
    {
        $this->expectException(InvalidFigure::class);
        $this->expectExceptionMessageIsOrContains('dead stock counts from 1 to 3650 days without a sale');

        Dormancy::isDormant(new StockFigure('SKU', 0, 0, 0, null), new DateTimeImmutable(self::NOW), $days);
    }

    /**
     * @return iterable<string, array{string|null, int|null}>
     */
    public static function ages(): iterable
    {
        yield 'never sold' => [null, null];
        yield 'ten days and some hours ago' => ['2026-11-10T07:00:00Z', 10];
        yield 'now' => [self::NOW, 0];
        yield 'three days after now' => ['2026-11-23T12:00:00Z', 0];
    }

    #[DataProvider('ages')]
    public function testTheDaysWithoutASale(?string $lastSoldAt, ?int $days): void
    {
        self::assertSame($days, Dormancy::daysWithoutSale(null === $lastSoldAt ? null : new DateTimeImmutable($lastSoldAt), new DateTimeImmutable(self::NOW)));
    }
}
