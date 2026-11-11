<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit;

use Phil\Geo\BoundingBox;
use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoundingBox::class)]
final class BoundingBoxTest extends TestCase
{
    public function testABoxHoldsEveryPointWithinTheDistance(): void
    {
        $montreal = Coordinates::of(45.5017, -73.5673);
        $box = BoundingBox::around($montreal, Distance::kilometers(10));

        self::assertEqualsWithDelta(45.5017 - 0.0899, $box->south, 0.0001);
        self::assertEqualsWithDelta(45.5017 + 0.0899, $box->north, 0.0001);
        self::assertEqualsWithDelta(-73.5673 - 0.1284, $box->west, 0.0001);
        self::assertEqualsWithDelta(-73.5673 + 0.1284, $box->east, 0.0001);

        foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $bearing) {
            $point = $this->pointAt($montreal, 9_999, $bearing);
            self::assertTrue($box->contains($point), (string) $bearing);
        }

        self::assertTrue($box->contains($montreal));
        self::assertFalse($box->contains(Coordinates::of(45.5017, -73.2)));
        self::assertFalse($box->contains(Coordinates::of(45.5017, -73.9)));
        self::assertFalse($box->contains(Coordinates::of(45.7, -73.5673)));
        self::assertFalse($box->contains(Coordinates::of(45.3, -73.5673)));
        self::assertTrue($box->contains(Coordinates::of($box->south, $box->west)));
        self::assertTrue($box->contains(Coordinates::of($box->north, $box->east)));
    }

    public function testNearAPoleTheBoxTakesEveryLongitude(): void
    {
        $north = BoundingBox::around(Coordinates::of(89.95, 10), Distance::kilometers(10));
        $south = BoundingBox::around(Coordinates::of(-89.95, 10), Distance::kilometers(10));

        self::assertSame([90.0, -180.0, 180.0], [$north->north, $north->west, $north->east]);
        self::assertEqualsWithDelta(89.86, $north->south, 0.01);
        self::assertSame([-90.0, -180.0, 180.0], [$south->south, $south->west, $south->east]);
    }

    public function testAcrossTheAntimeridianTheBoxTakesEveryLongitude(): void
    {
        $east = BoundingBox::around(Coordinates::of(0, 179.95), Distance::kilometers(10));
        $west = BoundingBox::around(Coordinates::of(0, -179.95), Distance::kilometers(10));

        self::assertSame([-180.0, 180.0], [$east->west, $east->east]);
        self::assertSame([-180.0, 180.0], [$west->west, $west->east]);
        self::assertEqualsWithDelta(-0.0899, $east->south, 0.0001);
        self::assertEqualsWithDelta(0.0899, $east->north, 0.0001);
    }

    public function testABoxThatReachesTheAntimeridianKeepsItsLongitudes(): void
    {
        $west = BoundingBox::around(Coordinates::of(0, -179.91006796362754), Distance::meters(10_000));
        $east = BoundingBox::around(Coordinates::of(0, 179.91006796362754), Distance::meters(10_000));

        self::assertSame(-180.0, $west->west);
        self::assertEqualsWithDelta(-179.8201, $west->east, 0.0001);
        self::assertSame(180.0, $east->east);
        self::assertEqualsWithDelta(179.8201, $east->west, 0.0001);
    }

    public function testAHugeRadiusTakesTheWholeEarth(): void
    {
        $box = BoundingBox::around(Coordinates::of(10, 20), Distance::kilometers(15_000));

        self::assertSame([-90.0, 90.0, -180.0, 180.0], [$box->south, $box->north, $box->west, $box->east]);
    }

    public function testABoxFarNorthIsWide(): void
    {
        $box = BoundingBox::around(Coordinates::of(80, 0), Distance::kilometers(1_000));

        self::assertEqualsWithDelta(-64.18, $box->west, 0.01);
        self::assertEqualsWithDelta(64.18, $box->east, 0.01);
        self::assertEqualsWithDelta(71.01, $box->south, 0.01);
    }

    private function pointAt(Coordinates $from, float $meters, float $bearing): Coordinates
    {
        $angle = $meters / Coordinates::EARTH_RADIUS;
        $latitude = deg2rad($from->latitude);
        $theta = deg2rad($bearing);
        $to = asin(sin($latitude) * cos($angle) + cos($latitude) * sin($angle) * cos($theta));
        $longitude = deg2rad($from->longitude) + atan2(sin($theta) * sin($angle) * cos($latitude), cos($angle) - sin($latitude) * sin($to));

        return Coordinates::of(rad2deg($to), rad2deg($longitude));
    }
}
