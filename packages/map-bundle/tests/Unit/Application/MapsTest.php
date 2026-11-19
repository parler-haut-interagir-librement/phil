<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\ListedPlace;
use Phil\MapBundle\Application\MapMessages;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Application\MapView;
use Phil\MapBundle\Application\Neighbourhood;
use Phil\MapBundle\Application\UnknownBoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\Booth;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Tests\Fixtures\FixedAvailability;
use Phil\MapBundle\Tests\Fixtures\FixedBaseLayer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

#[CoversClass(Maps::class)]
#[CoversClass(MapView::class)]
#[CoversClass(ListedPlace::class)]
#[CoversClass(UnknownBoothAvailability::class)]
final class MapsTest extends TestCase
{
    public function testPlacesAreMarkersWithEscapedInfoWindowsAndAList(): void
    {
        $view = $this->maps(new FixedBaseLayer(new TileLayer('/t/{z}/{x}/{y}', '© X'), ['k' => 'v']))->places('Our venues', [
            MapPlace::of('hall', 'Hall <b>', Coordinates::of(45.5, -73.6), 'Doors "9"', SiteAddress::of('/hall')),
            MapPlace::of('park', 'Park', Coordinates::of(45.6, -73.5)),
        ]);
        $map = $view->map->toArray();

        self::assertSame([MapView::PLACES, 'Our venues', 'Places on the map'], [$view->kind, $view->label, $view->listTitle]);
        self::assertEquals([new ListedPlace('Hall <b>', 'Doors "9"', '/hall'), new ListedPlace('Park')], $view->places);
        self::assertTrue($map['fitBoundsToMarkers']);
        self::assertNull($map['center']);
        self::assertSame(13.0, $map['zoom']);
        self::assertSame(['phil' => ['k' => 'v']], $map['extra']);
        self::assertSame(['url' => '/t/{z}/{x}/{y}', 'attribution' => '© X', 'options' => []], $this->at($map, 'options', 'tileLayer'));
        self::assertCount(2, (array) $this->at($map, 'markers'));
        self::assertSame(['lat' => 45.5, 'lng' => -73.6], $this->at($map, 'markers', 0, 'position'));
        self::assertSame('Hall <b>', $this->at($map, 'markers', 0, 'title'));
        self::assertSame('hall', $this->at($map, 'markers', 0, 'id'));
        self::assertSame(['place' => 'hall', 'href' => '/hall'], $this->at($map, 'markers', 0, 'extra'));
        self::assertSame('Hall &lt;b&gt;', $this->at($map, 'markers', 0, 'infoWindow', 'headerContent'));
        self::assertSame('Doors &quot;9&quot;', $this->at($map, 'markers', 0, 'infoWindow', 'content'));
        self::assertNull($this->at($map, 'markers', 1, 'infoWindow', 'content'));
        self::assertSame(['place' => 'park', 'href' => null], $this->at($map, 'markers', 1, 'extra'));
    }

    public function testAGivenCenterIsKeptAndNoExtraWithoutOne(): void
    {
        $map = $this->maps()->places('Map', [], Coordinates::of(10, 20), 9)->map->toArray();

        self::assertSame(['lat' => 10.0, 'lng' => 20.0], $map['center']);
        self::assertSame(9.0, $map['zoom']);
        self::assertFalse($map['fitBoundsToMarkers']);
        self::assertNull($map['extra']);
        self::assertFalse($this->at($map, 'options', 'tileLayer'));
    }

    public function testAMapWithoutPlacesNeedsACenter(): void
    {
        $this->expectExceptionObject(InvalidMap::because('A map without places is given its center.'));

        $this->maps()->places('Map', []);
    }

    public function testTheNumberOfPlacesIsBounded(): void
    {
        $places = static function (int $count): iterable {
            for ($i = 0; $i < $count; ++$i) {
                yield MapPlace::of('p'.$i, 'P', Coordinates::of(0, 0));
            }
        };

        self::assertCount(5000, $this->maps()->places('Map', $places(5000))->places);

        $this->expectExceptionObject(InvalidMap::because('A map shows 5000 places at most.'));
        $this->maps()->places('Map', $places(5001));
    }

    public function testTheNeighbourhoodShowsAreasAndTheirCounts(): void
    {
        $view = $this->maps()->neighbourhood('Members nearby', [
            Coordinates::of(45.5017, -73.5673),
            Coordinates::of(45.5019, -73.5670),
            Coordinates::of(10, 10),
        ], Coordinates::of(45.5, -73.6));
        $map = $view->map->toArray();

        self::assertSame([MapView::NEIGHBOURHOOD, 'Areas on the map'], [$view->kind, $view->listTitle]);
        self::assertSame(12.0, $map['zoom']);
        self::assertSame([], $map['markers']);
        self::assertCount(1, (array) $this->at($map, 'rectangles'));
        self::assertSame(['count' => 2], $this->at($map, 'rectangles', 0, 'extra'));
        self::assertSame('2 people in this area', $this->at($map, 'rectangles', 0, 'infoWindow', 'content'));
        self::assertEquals([new ListedPlace('2 people in this area', 'Around 45.5,-73.56')], $view->places);
        self::assertLessThan($this->at($map, 'rectangles', 0, 'northEast', 'lat'), $this->at($map, 'rectangles', 0, 'southWest', 'lat'));
        self::assertSame(11.0, $this->maps()->neighbourhood('M', [], Coordinates::of(0, 0), 11)->map->toArray()['zoom']);
    }

    public function testAFloorPlanWithoutPeriodHasItsBoothsUnknown(): void
    {
        $availability = new FixedAvailability(['b1' => BoothState::Free]);
        $view = $this->maps(availability: $availability)->floorPlan('Hall A', $this->plan());
        $map = $view->map->toArray();

        self::assertSame([], $availability->asked);
        self::assertSame([MapView::PLAN, 'Booths of the plan'], [$view->kind, $view->listTitle]);
        self::assertEquals([new ListedPlace('Booth <1>', 'Availability unknown'), new ListedPlace('Booth 2', 'Availability unknown')], $view->places);
        self::assertFalse($this->at($map, 'options', 'tileLayer'));
        self::assertArrayNotHasKey('attributionControlOptions', (array) $this->at($map, 'options'));
        self::assertSame(['lat' => -45.0, 'lng' => 90.0], $map['center']);
        self::assertSame([0.0, 0.0, 3.0], [$map['zoom'], $map['minZoom'], $map['maxZoom']]);
        self::assertSame(['phil' => ['plan' => ['image' => '/hall.png', 'tiles' => '/hall/{z}/{x}/{y}.png', 'nativeZoom' => 1, 'bounds' => [[-90.0, 0.0], [0.0, 180.0]]]]], $map['extra']);
        self::assertSame(['lat' => -15.0, 'lng' => 5.0], $this->at($map, 'rectangles', 0, 'southWest'));
        self::assertSame(['lat' => -5.0, 'lng' => 25.0], $this->at($map, 'rectangles', 0, 'northEast'));
        self::assertSame(['booth' => 'b1', 'state' => 'unknown'], $this->at($map, 'rectangles', 0, 'extra'));
        self::assertSame('b1', $this->at($map, 'rectangles', 0, 'id'));
        self::assertSame('Booth &lt;1&gt;', $this->at($map, 'rectangles', 0, 'infoWindow', 'headerContent'));
        self::assertSame('Availability unknown', $this->at($map, 'rectangles', 0, 'infoWindow', 'content'));
    }

    public function testAFloorPlanWithoutTilesHasNone(): void
    {
        $plan = FloorPlan::of(SiteAddress::of('/hall.png'), 360, 180, [Booth::of('b1', 'Booth', 10, 10, 40, 20)]);
        $map = $this->maps()->floorPlan('Hall A', $plan)->map->toArray();

        self::assertNull($this->at($map, 'extra', 'phil', 'plan', 'tiles'));
    }

    public function testAFloorPlanOverAPeriodAsksTheAvailability(): void
    {
        $availability = new FixedAvailability(['b1' => BoothState::Taken, 'b2' => BoothState::Free]);
        $view = $this->maps(availability: $availability)->floorPlan('Hall A', $this->plan(), new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable('2026-06-01T17:00:00Z'));
        $map = $view->map->toArray();

        self::assertSame([[['b1', 'b2'], '2026-06-01T09:00:00+00:00', '2026-06-01T17:00:00+00:00']], $availability->asked);
        self::assertEquals([new ListedPlace('Booth <1>', 'Taken'), new ListedPlace('Booth 2', 'Free')], $view->places);
        self::assertSame(['booth' => 'b2', 'state' => 'free'], $this->at($map, 'rectangles', 1, 'extra'));
    }

    public function testABoothTheAvailabilityForgetsIsUnknown(): void
    {
        $view = $this->maps(availability: new FixedAvailability(['b2' => BoothState::Closed]))->floorPlan('Hall', $this->plan(), new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable('2026-06-01T10:00:00Z'));

        self::assertSame(['Availability unknown', 'Closed'], array_map(static fn (ListedPlace $place): ?string => $place->description, $view->places));
    }

    public function testThePeriodOfAFloorPlanIsWhole(): void
    {
        $maps = $this->maps();

        foreach ([[new DateTimeImmutable('2026-06-01'), null], [null, new DateTimeImmutable('2026-06-01')], [new DateTimeImmutable('2026-06-02'), new DateTimeImmutable('2026-06-01')], [new DateTimeImmutable('2026-06-01'), new DateTimeImmutable('2026-06-01')]] as [$start, $end]) {
            try {
                $maps->floorPlan('Hall', $this->plan(), $start, $end);
                self::fail('The period is refused.');
            } catch (InvalidMap $error) {
                self::assertSame('The period of a floor plan has a start and an end after it, or neither.', $error->getMessage());
            }
        }
    }

    public function testTheUnknownAvailability(): void
    {
        self::assertSame(['b1' => BoothState::Unknown, 'b2' => BoothState::Unknown], new UnknownBoothAvailability()->states($this->plan()->booths, new DateTimeImmutable('2026-06-01'), new DateTimeImmutable('2026-06-02')));
    }

    private function plan(): FloorPlan
    {
        return FloorPlan::of(SiteAddress::of('/hall.png'), 360, 180, [
            Booth::of('b1', 'Booth <1>', 10, 10, 40, 20, 'r1'),
            Booth::of('b2', 'Booth 2', 100, 100, 50, 50),
        ], SiteAddress::of('/hall/{z}/{x}/{y}.png'));
    }

    private function maps(FixedBaseLayer $layer = new FixedBaseLayer(), FixedAvailability $availability = new FixedAvailability()): Maps
    {
        return new Maps($layer, new Neighbourhood(PrivacyGrid::of(1000, 2)), $availability, new MapMessages());
    }

    /**
     * The value under these keys of nested arrays.
     *
     * @param array<array-key, mixed> $data
     */
    private function at(array $data, string|int ...$keys): mixed
    {
        $value = $data;

        foreach ($keys as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }
}
