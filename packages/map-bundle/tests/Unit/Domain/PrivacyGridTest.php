<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Domain;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\GridCell;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrivacyGrid::class)]
#[CoversClass(GridCell::class)]
final class PrivacyGridTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $grid = PrivacyGrid::of();

        self::assertSame([1000, 3], [$grid->metres, $grid->minimum]);
    }

    public function testTwoNearbyPositionsShareTheirCellAndItsCenter(): void
    {
        $grid = PrivacyGrid::of(1000);
        $a = $grid->cellOf(Coordinates::of(45.50171, -73.56730));
        $b = $grid->cellOf(Coordinates::of(45.50199, -73.56701));

        self::assertSame($a->key(), $b->key());
        self::assertSame('5065:-5740', $a->key());
        self::assertSame([5065, -5740], [$a->row, $a->column]);
    }

    public function testACellIsAboutAsManyMetresHighAndWide(): void
    {
        $grid = PrivacyGrid::of(1000);
        [$southWest, $northEast] = $grid->bounds($grid->cellOf(Coordinates::of(45.5, -73.6)));
        $height = $southWest->distanceTo(Coordinates::of($northEast->latitude, $southWest->longitude))->meters;
        $width = $southWest->distanceTo(Coordinates::of($southWest->latitude, $northEast->longitude))->meters;

        self::assertEqualsWithDelta(1000, $height, 10);
        self::assertEqualsWithDelta(1000, $width, 10);
    }

    public function testTheCenterIsInsideTheCellAndNotThePosition(): void
    {
        $grid = PrivacyGrid::of(500);
        $position = Coordinates::of(48.8566, 2.3522);
        $cell = $grid->cellOf($position);
        $center = $grid->center($cell);
        [$southWest, $northEast] = $grid->bounds($cell);

        self::assertFalse($center->equals($position));
        self::assertEqualsWithDelta(($southWest->latitude + $northEast->latitude) / 2, $center->latitude, 1e-12);
        self::assertEqualsWithDelta(($southWest->longitude + $northEast->longitude) / 2, $center->longitude, 1e-12);
        self::assertGreaterThan($southWest->latitude, $position->latitude);
        self::assertLessThan($northEast->latitude, $position->latitude);
        self::assertGreaterThan($southWest->longitude, $position->longitude);
        self::assertLessThan($northEast->longitude, $position->longitude);
        self::assertSame($grid->cellOf($center)->key(), $cell->key(), 'the center falls in its own cell');
    }

    public function testNegativeCoordinatesFallInTheCellBelow(): void
    {
        $grid = PrivacyGrid::of(1000);

        self::assertSame('-1:-1', $grid->cellOf(Coordinates::of(-0.0001, -0.0001))->key());
        self::assertSame('0:0', $grid->cellOf(Coordinates::of(0.0001, 0.0001))->key());
    }

    public function testCellsAtTheEdgesOfTheWorldStayInIt(): void
    {
        $grid = PrivacyGrid::of(50_000);

        foreach ([[90.0, 180.0], [-90.0, -180.0], [89.9, 179.9], [-89.9, -179.9]] as [$latitude, $longitude]) {
            [$southWest, $northEast] = $grid->bounds($grid->cellOf(Coordinates::of($latitude, $longitude)));

            self::assertGreaterThanOrEqual(-90.0, $southWest->latitude);
            self::assertLessThanOrEqual(90.0, $northEast->latitude);
            self::assertGreaterThanOrEqual(-180.0, $southWest->longitude);
            self::assertLessThanOrEqual(180.0, $northEast->longitude);
        }
    }

    public function testColumnsStopNarrowingNearThePoles(): void
    {
        $grid = PrivacyGrid::of(100);
        [$southWest, $northEast] = $grid->bounds($grid->cellOf(Coordinates::of(89.999, 10.0)));

        self::assertEqualsWithDelta(100 / (111_320 * 0.01), $northEast->longitude - $southWest->longitude, 1e-9);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function refused(): iterable
    {
        yield 'too small' => [99, 3, 'A cell of the grid is 100 to 50000 metres wide, not 99.'];
        yield 'too large' => [50_001, 3, 'A cell of the grid is 100 to 50000 metres wide, not 50001.'];
        yield 'no minimum' => [1000, 0, 'A cell is shown with 1 to 1000 positions at least, not 0.'];
        yield 'minimum too large' => [1000, 1001, 'A cell is shown with 1 to 1000 positions at least, not 1001.'];
    }

    #[DataProvider('refused')]
    public function testARefusedGrid(int $metres, int $minimum, string $message): void
    {
        $this->expectExceptionObject(InvalidMap::because($message));

        PrivacyGrid::of($metres, $minimum);
    }

    public function testTheBoundsAreAccepted(): void
    {
        self::assertSame([100, 1], [PrivacyGrid::of(100, 1)->metres, PrivacyGrid::of(100, 1)->minimum]);
        self::assertSame([50_000, 1000], [PrivacyGrid::of(50_000, 1000)->metres, PrivacyGrid::of(50_000, 1000)->minimum]);
    }
}
