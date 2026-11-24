<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceReportsBundle\Infrastructure\Logistics\LogisticsStockFigures;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogisticsStockFigures::class)]
final class LogisticsStockFiguresTest extends TestCase
{
    public function testTheFiguresOfASkuAcrossItsLocations(): void
    {
        $main = $this->level('main', 10, 3);
        $north = $this->level('north', 4, 0);
        $repository = $this->repository([$main, $north], [
            'main' => [
                $this->move($main, MovementKind::Received, 8, '2026-10-01T00:00:00Z'),
                $this->move($main, MovementKind::Shipped, -2, '2026-10-15T00:00:00Z'),
                $this->move($main, MovementKind::Received, 5, '2026-11-01T00:00:00Z'),
                $this->move($main, MovementKind::Counted, -1, '2026-11-05T00:00:00Z'),
                $this->move($main, MovementKind::TransferredOut, -2, '2026-11-06T00:00:00Z'),
                $this->move($main, MovementKind::Shipped, -1, '2026-11-20T10:00:00Z'),
                $this->move($main, MovementKind::Shipped, 0, '2026-11-25T00:00:00Z'),
                $this->move($main, MovementKind::Received, 9, '2026-12-01T00:00:00Z'),
            ],
            'north' => [
                $this->move($north, MovementKind::TransferredIn, 2, '2026-11-06T00:00:00Z'),
                $this->move($north, MovementKind::Received, 2, '2026-11-30T23:59:59Z'),
                $this->move($north, MovementKind::Shipped, -1, '2026-11-02T00:00:00Z'),
                $this->move($north, MovementKind::Reserved, 0, '2026-11-26T00:00:00Z'),
            ],
        ]);

        $figure = new LogisticsStockFigures($repository)->of('BOOK', Lines::period());

        self::assertSame('BOOK', $figure->sku);
        self::assertSame(6, $figure->start);
        self::assertSame(7, $figure->received);
        self::assertSame(11, $figure->available);
        self::assertEquals(new DateTimeImmutable('2026-11-20T10:00:00Z'), $figure->lastSoldAt);
    }

    public function testASkuWithoutStockHasNoFigures(): void
    {
        $figure = new LogisticsStockFigures($this->repository([], []))->of('NONE', Lines::period());

        self::assertSame([0, 0, 0, null], [$figure->start, $figure->received, $figure->available, $figure->lastSoldAt]);
    }

    public function testUnitsTakenBackOnReceptionNeverMakeTheReceivedNegative(): void
    {
        $main = $this->level('main', 0, 0);
        $repository = $this->repository([$main], ['main' => [$this->move($main, MovementKind::Received, -3, '2026-11-03T00:00:00Z')]]);

        self::assertSame(0, new LogisticsStockFigures($repository)->of('BOOK', Lines::period())->received);
    }

    public function testOfTwoSalesAtTheSameMomentTheLastReadIsKept(): void
    {
        $main = $this->level('main', 1, 0);
        $repository = $this->repository([$main], ['main' => [
            $this->move($main, MovementKind::Shipped, -1, '2026-11-03T12:00:00+00:00'),
            $this->move($main, MovementKind::Shipped, -1, '2026-11-03T13:00:00+01:00'),
            $this->move($main, MovementKind::Shipped, -1, '2026-11-02T00:00:00+00:00'),
        ]]);

        self::assertSame('2026-11-03T13:00:00+01:00', new LogisticsStockFigures($repository)->of('BOOK', Lines::period())->lastSoldAt?->format(\DATE_ATOM));
    }

    private function level(string $location, int $onHand, int $reserved): StockLevel
    {
        $level = StockLevel::of('BOOK', Location::of($location, ucfirst($location)));

        if ($onHand > 0) {
            $level->receive($onHand);
        }

        if ($reserved > 0) {
            $level->reserve($reserved);
        }

        return $level;
    }

    private function move(StockLevel $level, MovementKind $kind, int $change, string $at): StockMovement
    {
        return StockMovement::of($level, $kind, $change, 0, null, new DateTimeImmutable($at));
    }

    /**
     * @param list<StockLevel>                   $levels
     * @param array<string, list<StockMovement>> $journals
     */
    private function repository(array $levels, array $journals): StockRepository
    {
        return new readonly class($levels, $journals) implements StockRepository {
            /**
             * @param list<StockLevel>                   $levels
             * @param array<string, list<StockMovement>> $journals
             */
            public function __construct(
                private array $levels,
                private array $journals,
            ) {
            }

            #[Override]
            public function level(string $sku, string $location): ?StockLevel
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function levelsOf(string $sku): array
            {
                return array_values(array_filter($this->levels, static fn (StockLevel $level): bool => $level->sku === $sku));
            }

            #[Override]
            public function levelsOfSkus(array $skus): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addLevel(StockLevel $level): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function record(StockMovement $movement): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function journal(string $sku, string $location): array
            {
                return $this->journals[$location] ?? [];
            }

            #[Override]
            public function reservationsOf(string $orderNumber): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addReservation(Reservation $reservation): void
            {
                throw new LogicException('Not written.');
            }
        };
    }
}
