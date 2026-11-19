<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Application;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Neighbourhood;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\ApproximateArea;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Neighbourhood::class)]
#[CoversClass(ApproximateArea::class)]
final class NeighbourhoodTest extends TestCase
{
    public function testOnlyTheCellsWithTheMinimumComeOutAndNeverAPosition(): void
    {
        $grid = PrivacyGrid::of(1000, 2);
        $positions = [
            Coordinates::of(45.5017, -73.5673),
            Coordinates::of(45.5019, -73.5670),
            Coordinates::of(45.5018, -73.5671),
            Coordinates::of(46.8139, -71.2080),
            Coordinates::of(46.8140, -71.2081),
            Coordinates::of(48.8566, 2.3522),
        ];

        $areas = new Neighbourhood($grid)->areas($positions);

        self::assertSame([3, 2], array_map(static fn (ApproximateArea $area): int => $area->count, $areas));

        foreach ($areas as $area) {
            foreach ($positions as $position) {
                self::assertFalse($area->center->equals($position));
            }
        }

        $cell = $grid->cellOf($positions[0]);
        self::assertTrue($areas[0]->center->equals($grid->center($cell)));
        self::assertTrue($areas[0]->southWest->equals($grid->bounds($cell)[0]));
        self::assertTrue($areas[0]->northEast->equals($grid->bounds($cell)[1]));
    }

    public function testCellsOfEqualCountsGoByRowThenColumn(): void
    {
        $grid = PrivacyGrid::of(1000, 1);
        $areas = new Neighbourhood($grid)->areas([
            Coordinates::of(10.0, 10.0),
            Coordinates::of(1.0, 20.0),
            Coordinates::of(1.0, 5.0),
        ]);

        self::assertSame(
            [$grid->cellOf(Coordinates::of(1.0, 5.0))->key(), $grid->cellOf(Coordinates::of(1.0, 20.0))->key(), $grid->cellOf(Coordinates::of(10.0, 10.0))->key()],
            array_map(static fn (ApproximateArea $area): string => $grid->cellOf($area->center)->key(), $areas),
        );
    }

    public function testACellOfExactlyTheMinimumIsShown(): void
    {
        $areas = new Neighbourhood(PrivacyGrid::of(1000, 3))->areas([Coordinates::of(1, 1), Coordinates::of(1, 1), Coordinates::of(1, 1)]);

        self::assertCount(1, $areas);
        self::assertSame([], new Neighbourhood(PrivacyGrid::of(1000, 3))->areas([Coordinates::of(1, 1), Coordinates::of(1, 1)]));
    }

    public function testACellBelowTheMinimumDoesNotHideTheNext(): void
    {
        $areas = new Neighbourhood(PrivacyGrid::of(1000, 2))->areas([
            Coordinates::of(10.0, 10.0),
            Coordinates::of(1.0, 5.0),
            Coordinates::of(1.0, 5.0),
        ]);

        self::assertSame([2], array_map(static fn (ApproximateArea $area): int => $area->count, $areas));
    }

    public function testTheNumberOfPositionsReadIsBounded(): void
    {
        $neighbourhood = new Neighbourhood(PrivacyGrid::of(1000, 1));
        $positions = static function (int $count): iterable {
            for ($i = 0; $i < $count; ++$i) {
                yield Coordinates::of(0.5, 0.5);
            }
        };

        self::assertSame(100_000, $neighbourhood->areas($positions(100_000))[0]->count);

        $this->expectExceptionObject(InvalidMap::because('The neighbourhood layer reads 100000 positions at most.'));
        $neighbourhood->areas($positions(100_001));
    }
}
