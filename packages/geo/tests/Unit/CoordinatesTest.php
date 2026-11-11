<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit;

use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use Phil\Geo\Exception\GeoException;
use Phil\Geo\Exception\InvalidCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Coordinates::class)]
#[CoversClass(InvalidCoordinates::class)]
final class CoordinatesTest extends TestCase
{
    public function testAPointHasALatitudeAndALongitude(): void
    {
        $montreal = Coordinates::of(45.5017, -73.5673);

        self::assertSame([45.5017, -73.5673], [$montreal->latitude, $montreal->longitude]);
        self::assertSame('45.5017,-73.5673', $montreal->toString());
        self::assertSame('45.5017,-73.5673', (string) $montreal);
    }

    public function testTheBoundsAreAccepted(): void
    {
        self::assertSame('90,180', Coordinates::of(90, 180)->toString());
        self::assertSame('-90,180', Coordinates::of(-90, -180)->toString());
        self::assertTrue(Coordinates::of(0, -180)->equals(Coordinates::of(0, 180)));
        self::assertSame(-179.9, Coordinates::of(0, -179.9)->longitude);
    }

    /**
     * @return iterable<string, array{float, float, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'north of the pole' => [90.000001, 0, 'A latitude is -90 to 90 degrees; 90.000001 is not.'];
        yield 'south of the pole' => [-90.000001, 0, 'A latitude is -90 to 90 degrees; -90.000001 is not.'];
        yield 'no latitude' => [\NAN, 0, 'A latitude is -90 to 90 degrees; NAN is not.'];
        yield 'east of the antimeridian' => [0, 180.000001, 'A longitude is -180 to 180 degrees; 180.000001 is not.'];
        yield 'west of the antimeridian' => [0, -180.000001, 'A longitude is -180 to 180 degrees; -180.000001 is not.'];
        yield 'no longitude' => [0, \NAN, 'A longitude is -180 to 180 degrees; NAN is not.'];
        yield 'an infinite latitude' => [\INF, 0, 'A latitude is -90 to 90 degrees; INF is not.'];
        yield 'an infinite longitude' => [0, -\INF, 'A longitude is -180 to 180 degrees; -INF is not.'];
    }

    #[DataProvider('outOfBounds')]
    public function testAPointIsOnTheEarth(float $latitude, float $longitude, string $message): void
    {
        $this->expectException(InvalidCoordinates::class);
        $this->expectExceptionMessageIsOrContains($message);

        Coordinates::of($latitude, $longitude);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'plain' => ['45.5017,-73.5673', '45.5017,-73.5673'];
        yield 'spaced' => [' 45.5017 , -73.5673 ', '45.5017,-73.5673'];
        yield 'whole degrees' => ['45,-73', '45,-73'];
        yield 'three digits' => ['-12.5,-123.5', '-12.5,-123.5'];
        yield 'many decimals' => ['45.123456789,-73.000000049', '45.1234568,-73'];
        yield 'zero' => ['-0.00000001,0', '0,0'];
    }

    #[DataProvider('texts')]
    public function testAPointIsReadFromText(string $text, string $written): void
    {
        self::assertSame($written, Coordinates::fromString($text)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTexts(): iterable
    {
        yield 'one number' => ['45.5'];
        yield 'a semicolon' => ['45.5;-73.5'];
        yield 'a letter' => ['45.5N,73.5W'];
        yield 'four digits' => ['1045.5,-73.5'];
        yield 'a dot alone' => ['45.,-73.5'];
        yield 'a trailing newline' => ["45.5,-73.5\n"];
        yield 'a tab' => ["45.5,\t-73.5"];
        yield 'text before' => ['x45.5,-73.5'];
    }

    #[DataProvider('badTexts')]
    public function testOtherTextIsRefused(string $text): void
    {
        $this->expectException(InvalidCoordinates::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a "latitude,longitude" pair in decimal degrees.', $text));

        Coordinates::fromString($text);
    }

    public function testAnOutOfBoundsTextIsRefusedToo(): void
    {
        $this->expectException(InvalidCoordinates::class);

        Coordinates::fromString('91,0');
    }

    public function testEqualPointsAreEqual(): void
    {
        $point = Coordinates::of(45.5, -73.5);

        self::assertTrue($point->equals(Coordinates::of(45.5, -73.5)));
        self::assertFalse($point->equals(Coordinates::of(45.5, -73.4)));
        self::assertFalse($point->equals(Coordinates::of(45.4, -73.5)));
    }

    public function testABlurredPointKeepsFewerDecimals(): void
    {
        $point = Coordinates::of(45.501689, -73.567256);

        self::assertSame('45.5,-73.57', $point->blurred(2)->toString());
        self::assertSame('46,-74', $point->blurred(0)->toString());
        self::assertSame('45.501689,-73.567256', $point->blurred(7)->toString());
        self::assertTrue(Coordinates::of(0, -179.996)->blurred(2)->equals(Coordinates::of(0, 180)));

        foreach ([-1, 8] as $decimals) {
            try {
                $point->blurred($decimals);
                self::fail('A bad precision was accepted.');
            } catch (InvalidCoordinates $invalid) {
                self::assertSame(\sprintf('Coordinates keep 0 to 7 decimals; %d is refused.', $decimals), $invalid->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{Coordinates, Coordinates, float}>
     */
    public static function distances(): iterable
    {
        yield 'the same place' => [Coordinates::of(45.5017, -73.5673), Coordinates::of(45.5017, -73.5673), 0.0];
        yield 'Montréal to Québec' => [Coordinates::of(45.5017, -73.5673), Coordinates::of(46.8139, -71.208), 233_029.30001217];
        yield 'Montréal to Toronto' => [Coordinates::of(45.5017, -73.5673), Coordinates::of(43.6532, -79.3832), 504_262.72200486];
        yield 'half the Earth' => [Coordinates::of(0, 0), Coordinates::of(0, 180), \M_PI * Coordinates::EARTH_RADIUS];
        yield 'pole to pole' => [Coordinates::of(90, 0), Coordinates::of(-90, 0), \M_PI * Coordinates::EARTH_RADIUS];
        yield 'antipodes off the equator' => [Coordinates::of(45, 10), Coordinates::of(-45, -170), \M_PI * Coordinates::EARTH_RADIUS];
        yield 'one degree of latitude' => [Coordinates::of(0, 0), Coordinates::of(1, 0), \M_PI * Coordinates::EARTH_RADIUS / 180];
        yield 'one degree of longitude at 60°' => [Coordinates::of(60, 0), Coordinates::of(60, 1), 55_597.01086];
    }

    #[DataProvider('distances')]
    public function testTheDistanceIsTheGreatCircle(Coordinates $from, Coordinates $to, float $meters): void
    {
        self::assertEqualsWithDelta($meters, $from->distanceTo($to)->meters, 0.01);
        self::assertEqualsWithDelta($meters, $to->distanceTo($from)->meters, 0.01);
    }

    public function testTheEarthHasItsMeanRadius(): void
    {
        self::assertSame(6_371_008.8, Coordinates::EARTH_RADIUS);
        self::assertInstanceOf(Distance::class, Coordinates::of(0, 0)->distanceTo(Coordinates::of(0, 0)));
        self::assertInstanceOf(GeoException::class, InvalidCoordinates::text('x'));
    }
}
