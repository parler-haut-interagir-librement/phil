<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit;

use DateTimeImmutable;
use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Availability;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EventForecast;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\Internal\JsonClient;
use Phil\EventWeather\Internal\Polygon;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Phil\Geo\Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The edges of each limit, range and comparison of the package: the value on the line and the one beside it.
 */
#[CoversClass(AlertSeverity::class)]
#[CoversClass(EventForecast::class)]
#[CoversClass(EventLocation::class)]
#[CoversClass(WeatherUnavailable::class)]
#[CoversClass(Data::class)]
#[CoversClass(JsonClient::class)]
#[CoversClass(Polygon::class)]
#[CoversClass(LocationforecastProvider::class)]
#[CoversClass(CitypageWeatherProvider::class)]
final class BoundaryTest extends TestCase
{
    public function testSeveritiesAreOrderedFromExtremeToUnknown(): void
    {
        self::assertTrue(AlertSeverity::Extreme->isAtLeast(AlertSeverity::Extreme));
        self::assertTrue(AlertSeverity::Extreme->isAtLeast(AlertSeverity::Severe));
        self::assertFalse(AlertSeverity::Severe->isAtLeast(AlertSeverity::Extreme));
        self::assertFalse(AlertSeverity::Unknown->isAtLeast(AlertSeverity::Minor));
        self::assertTrue(AlertSeverity::Minor->isAtLeast(AlertSeverity::Unknown));
    }

    public function testTheHighestChanceIsZeroWhenTheFirstPeriodGivesNone(): void
    {
        $start = new DateTimeImmutable('2026-11-13T12:00:00Z');
        $period = static fn (?int $chance): ForecastPeriod => new ForecastPeriod($start, $start->modify('+1 hour'), Condition::Clear, precipitationProbability: $chance);

        self::assertSame(0, new EventForecast(Availability::Available, $start, $start, periods: [$period(null), $period(0)])->maxPrecipitationProbability());
        self::assertSame(40, new EventForecast(Availability::Available, $start, $start, periods: [$period(null), $period(40), $period(10)])->maxPrecipitationProbability());
        self::assertNull(new EventForecast(Availability::Available, $start, $start, periods: [$period(null)])->maxPrecipitationProbability());
    }

    public function testACountryIsTwoLettersAndNothingElse(): void
    {
        self::assertSame('CA', new EventLocation(Coordinates::of(45, -73), 'ca')->country);

        $this->expectException(InvalidArgument::class);

        new EventLocation(Coordinates::of(45, -73), "CA\n");
    }

    public function testWhatWentWrongHasNoCode(): void
    {
        $previous = new RuntimeException('cause');

        self::assertSame(0, WeatherUnavailable::transport('P', $previous)->getCode());
        self::assertSame(0, WeatherUnavailable::format('P', 'why', $previous)->getCode());
        self::assertSame($previous, WeatherUnavailable::format('P', 'why', $previous)->getPrevious());
    }

    public function testAnInstantStartsAtTheBeginningOfTheText(): void
    {
        self::assertNull(Data::instant('now 2026-11-13T12:00:00Z'));
    }

    public function testTheClientReadsSixtyThreeLevelsAndNoMore(): void
    {
        $client = static fn (int $levels): JsonClient => new JsonClient(new MockHttpClient(new MockResponse(str_repeat('[', $levels).str_repeat(']', $levels))), new Contact('App/1', 'a@b.example'), 'P');

        self::assertIsArray($client(63)->get('https://a.example/'));

        $this->expectException(WeatherUnavailable::class);
        $this->expectExceptionMessageIsOrContains('not JSON');

        $client(64)->get('https://a.example/');
    }

    public function testAPolygonCountsItsEdgesOnTheRightOfThePoint(): void
    {
        $square = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]];
        $triangle = ['type' => 'Polygon', 'coordinates' => [[[1, 1], [9, 3], [4, 8], [1, 1]]]];
        $open = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10]]]];
        $unreadable = ['type' => 'Polygon', 'coordinates' => [[['x', 0], [10, 0], [10, 10], [0, 10], ['x', 0]]]];

        self::assertFalse(Polygon::contains($square, Coordinates::of(10, 5)));
        self::assertTrue(Polygon::contains($square, Coordinates::of(0, 5)));
        self::assertFalse(Polygon::contains($square, Coordinates::of(5, 10)));
        self::assertTrue(Polygon::contains($square, Coordinates::of(0, 0)));
        self::assertTrue(Polygon::contains($square, Coordinates::of(0, 5)));
        self::assertTrue(Polygon::contains($open, Coordinates::of(5, 1)));
        self::assertFalse(Polygon::contains($unreadable, Coordinates::of(5, 5)));

        foreach ([[5, 5, true], [2, 5, false], [6, 2, false], [1, 1, false], [3, 3, true], [4, 4, true]] as [$latitude, $longitude, $inside]) {
            self::assertSame($inside, Polygon::contains($triangle, Coordinates::of($latitude, $longitude)), $latitude.','.$longitude);
        }
    }

    public function testAMultiPolygonDoesNotContainWhatNoneOfItsPolygonsContain(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [[[[20, 20], [30, 20], [30, 30], [20, 20]]], [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]]];

        self::assertFalse(Polygon::contains($multi, Coordinates::of(50, 50)));
        self::assertTrue(Polygon::contains($multi, Coordinates::of(5, 5)));
    }

    public function testASiteOnTheEdgeOfTheGlobeIsKept(): void
    {
        foreach ([[-90.0, 0.0, [0, -90]], [90.0, 0.0, [0, 90]], [0.0, 180.0, [180, 0]], [0.0, -180.0, [-180, 0]]] as [$latitude, $longitude, $coordinates]) {
            $body = json_encode(['features' => [['geometry' => ['coordinates' => $coordinates], 'properties' => ['lastUpdated' => '2026-11-13T09:00:00Z']]]], \JSON_THROW_ON_ERROR);
            $forecast = new CitypageWeatherProvider(new MockHttpClient(new MockResponse($body)), $this->contact())->forecast(new EventLocation(Coordinates::of($latitude, $longitude), 'CA'));

            self::assertSame('2026-11-13T09:00:00+00:00', $forecast->issuedAt->format(\DATE_ATOM));
        }
    }

    public function testAnUnreadableSiteDoesNotStopTheSearch(): void
    {
        $body = '{"features":[{"geometry":{"coordinates":["x",45.5]}},{"geometry":{"coordinates":[-73.58,45.5]},"properties":{"lastUpdated":"2026-11-13T09:00:00Z"}}]}';
        $forecast = new CitypageWeatherProvider(new MockHttpClient(new MockResponse($body)), $this->contact())->forecast(new EventLocation(Coordinates::of(45.5017, -73.5673), 'CA'));

        self::assertSame('2026-11-13T09:00:00+00:00', $forecast->issuedAt->format(\DATE_ATOM));
    }

    public function testTheSmallestSearchDistanceIsOneKilometer(): void
    {
        self::assertTrue(new CitypageWeatherProvider(new MockHttpClient(), $this->contact(), searchKilometers: 1)->supports(new EventLocation(Coordinates::of(0, 0), 'CA')));
    }

    public function testMetNorwayClampsChancesAndRoundsWinds(): void
    {
        $entry = static fn (int $hour, float|int $chance, float $wind, int $direction): array => [
            'time' => \sprintf('2026-11-13T%02d:00:00Z', $hour),
            'data' => [
                'instant' => ['details' => ['wind_speed' => $wind, 'wind_speed_of_gust' => $wind, 'wind_from_direction' => $direction]],
                'next_1_hours' => ['summary' => ['symbol_code' => 'fair_day'], 'details' => ['probability_of_precipitation' => $chance]],
            ],
        ];
        $body = json_encode(['properties' => ['meta' => ['updated_at' => '2026-11-13T10:00:00Z'], 'timeseries' => [
            $entry(12, 100, 5.123, 400),
            $entry(13, 101, 1.0, -10),
            $entry(14, -5, 1.0, 359),
            $entry(15, 0, 1.0, 0),
            $entry(16, 1, 1.0, 360),
        ]]], \JSON_THROW_ON_ERROR);

        $periods = new LocationforecastProvider(new MockHttpClient(new MockResponse($body)), $this->contact())->forecast(new EventLocation(Coordinates::of(45, -73)))->periods;

        self::assertSame([100, 100, 0, 0, 1], array_map(static fn (ForecastPeriod $period): ?int => $period->precipitationProbability, $periods));
        self::assertSame([18.4, 18.4], [$periods[0]->windSpeed, $periods[0]->windGust]);
        self::assertSame([40, 350, 359, 0, 0], array_map(static fn (ForecastPeriod $period): ?int => $period->windDirection, $periods));
    }

    private function contact(): Contact
    {
        return new Contact('EventsApp/1.4', 'ops@events.example');
    }
}
