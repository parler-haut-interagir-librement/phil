<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EnvironmentCanada\WeatherAlertsSource;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\Internal\JsonClient;
use Phil\EventWeather\Internal\Polygon;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Phil\EventWeather\WeatherAlert;
use Phil\Geo\Coordinates;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(LocationforecastProvider::class)]
#[CoversClass(CitypageWeatherProvider::class)]
#[CoversClass(WeatherAlertsSource::class)]
#[CoversClass(JsonClient::class)]
#[CoversClass(Data::class)]
#[CoversClass(Polygon::class)]
#[CoversClass(WeatherUnavailable::class)]
final class ProvidersTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures/';

    /**
     * @var list<array{string, string, array<mixed>}>
     */
    private array $requests = [];

    public function testMetNorwayReadsLocationforecast(): void
    {
        $provider = new LocationforecastProvider($this->client('met-locationforecast.json'), $this->contact());
        $forecast = $provider->forecast($this->montreal());

        self::assertSame('GET', $this->requests[0][0]);
        self::assertSame('https://api.met.no/weatherapi/locationforecast/2.0/complete?lat=45.5017&lon=-73.5673', $this->requests[0][1]);
        self::assertContains('User-Agent: EventsApp/1.4 ops@events.example', $this->headers($this->requests[0][2]));
        self::assertEquals(10, $this->requests[0][2]['timeout']);
        self::assertEquals(20, $this->requests[0][2]['max_duration']);
        self::assertSame(3, $this->requests[0][2]['max_redirects']);
        self::assertSame('2026-11-13T10:41:07+00:00', $forecast->issuedAt->format(\DATE_ATOM));
        self::assertEquals(Attribution::metNorway(), $forecast->attribution);
        self::assertEquals(Attribution::metNorway(), $provider->attribution());
        self::assertTrue($provider->supports(new EventLocation(Coordinates::of(0, 0))));
        self::assertSame(216, $provider->horizonHours());
        self::assertCount(3, $forecast->periods);

        [$first, $second, $third] = $forecast->periods;
        self::assertSame(['2026-11-13T12:00:00+00:00', '2026-11-13T13:00:00+00:00'], [$first->from->format(\DATE_ATOM), $first->to->format(\DATE_ATOM)]);
        self::assertSame([Condition::Showers, 4.2, 63, 0.4, 18.0, 34.2, 225, null, null, 'lightrainshowers_day'], [$first->condition, $first->temperature, $first->precipitationProbability, $first->precipitation, $first->windSpeed, $first->windGust, $first->windDirection, $first->minTemperature, $first->maxTemperature, $first->summary]);
        self::assertSame([Condition::Cloudy, 14.4, null, 0, 12], [$second->condition, $second->windSpeed, $second->windGust, $second->windDirection, $second->precipitationProbability]);
        self::assertSame(['2026-11-15T18:00:00+00:00', '2026-11-16T00:00:00+00:00'], [$third->from->format(\DATE_ATOM), $third->to->format(\DATE_ATOM)]);
        self::assertSame([Condition::Snow, 90, 8.5, -2.4, 0.2, null], [$third->condition, $third->precipitationProbability, $third->precipitation, $third->minTemperature, $third->maxTemperature, $third->windDirection]);
    }

    public function testMetNorwayCutsTheCoordinatesToFourDecimals(): void
    {
        self::assertSame('45.5017', LocationforecastProvider::truncated(45.50179));
        self::assertSame('-73.5673', LocationforecastProvider::truncated(-73.56739));
        self::assertSame('0.0000', LocationforecastProvider::truncated(0.0));
        self::assertSame('12.0000', LocationforecastProvider::truncated(12.0));

        new LocationforecastProvider($this->client('met-locationforecast.json'), $this->contact(), Url::fromString('https://proxy.example/forecast'))
            ->forecast(new EventLocation(Coordinates::of(-33.86789, 151.20999)));

        self::assertSame('https://proxy.example/forecast?lat=-33.8678&lon=151.2099', $this->requests[0][1]);
    }

    public function testMetNorwayRefusesAnAnswerWithoutItsSeries(): void
    {
        foreach (['{"properties":{"meta":{"updated_at":"2026-11-13T10:41:07Z"}}}', '{"properties":{"meta":{},"timeseries":[]}}', '{"properties":{"meta":{"updated_at":"2026-11-13T10:41:07Z"},"timeseries":{"a":1}}}'] as $body) {
            try {
                new LocationforecastProvider(new MockHttpClient(new MockResponse($body)), $this->contact())->forecast($this->montreal());
                self::fail('Read: '.$body);
            } catch (WeatherUnavailable $exception) {
                self::assertSame('MET Norway sent what its format does not describe: no "properties.meta.updated_at" or "properties.timeseries"', $exception->getMessage());
            }
        }

        self::assertSame([], new LocationforecastProvider(new MockHttpClient(new MockResponse('{"properties":{"meta":{"updated_at":"2026-11-13T10:41:07Z"},"timeseries":[]}}')), $this->contact())->forecast($this->montreal())->periods);
    }

    public function testEnvironmentCanadaReadsTheNearestCityPage(): void
    {
        $provider = new CitypageWeatherProvider($this->client('eccc-citypage.json'), $this->contact(), 'fr');
        $forecast = $provider->forecast($this->montreal());

        self::assertStringStartsWith('https://api.weather.gc.ca/collections/citypageweather-realtime/items?f=json&lang=fr&limit=100&bbox=-74.', $this->requests[0][1]);
        self::assertMatchesRegularExpression('/bbox=-74\.20\d*%2C45\.05\d*%2C-72\.92\d*%2C45\.95\d*$/', $this->requests[0][1]);
        self::assertContains('User-Agent: EventsApp/1.4 ops@events.example', $this->headers($this->requests[0][2]));
        self::assertSame('2026-11-13T11:00:00+00:00', $forecast->issuedAt->format(\DATE_ATOM));
        self::assertEquals(Attribution::environmentCanada(), $forecast->attribution);
        self::assertEquals(Attribution::environmentCanada(), $provider->attribution());
        self::assertSame(24, $provider->horizonHours());
        self::assertCount(2, $forecast->periods);

        [$first, $second] = $forecast->periods;
        self::assertSame(['2026-11-13T12:00:00+00:00', '2026-11-13T13:00:00+00:00'], [$first->from->format(\DATE_ATOM), $first->to->format(\DATE_ATOM)]);
        self::assertSame([Condition::Showers, 5.0, 40, 20.0, 40.0, 225, "Possibilité d'averses"], [$first->condition, $first->temperature, $first->precipitationProbability, $first->windSpeed, $first->windGust, $first->windDirection, $first->summary]);
        self::assertSame([Condition::Clear, 0, null, null, 'Généralement ensoleillé'], [$second->condition, $second->precipitationProbability, $second->windSpeed, $second->windDirection, $second->summary]);
    }

    public function testEnvironmentCanadaServesCanadianPlacesOnly(): void
    {
        $provider = new CitypageWeatherProvider(new MockHttpClient(), $this->contact());

        self::assertTrue($provider->supports(new EventLocation(Coordinates::of(45.5, -73.5), 'ca')));
        self::assertFalse($provider->supports(new EventLocation(Coordinates::of(45.5, -73.5), 'US')));
        self::assertFalse($provider->supports(new EventLocation(Coordinates::of(45.5, -73.5))));
    }

    public function testEnvironmentCanadaNeedsASiteNearTheEvent(): void
    {
        $far = '{"features":[{"geometry":{"coordinates":[-79.4,43.7]},"properties":{}},{"geometry":{"coordinates":["x",43.7]}},{"geometry":{"coordinates":[-73.5,95]}},{"geometry":{"coordinates":[-200,45]}}]}';

        foreach ([$far, '{"features":[]}', '{}'] as $body) {
            try {
                new CitypageWeatherProvider(new MockHttpClient(new MockResponse($body)), $this->contact())->forecast($this->montreal());
                self::fail('Read: '.$body);
            } catch (WeatherUnavailable $exception) {
                self::assertSame('Environment Canada has no forecast site near the event.', $exception->getMessage());
            }
        }
    }

    public function testEnvironmentCanadaFallsBackToTheTimeOfTheSite(): void
    {
        $body = '{"features":[{"geometry":{"coordinates":[-73.58,45.5]},"properties":{"lastUpdated":"2026-11-13T09:00:00Z","forecastGroup":{"hourlyForecastGroup":{"hourlyForecasts":[{"timestamp":"2026-11-13T12:00:00Z","lop":{"value":{"en":140}}},{"timestamp":"2026-11-13T13:00:00Z","condition":{"en":"Cloudy"},"lop":{"value":{"en":-5}}}]}}}}]}';
        $forecast = new CitypageWeatherProvider(new MockHttpClient(new MockResponse($body)), $this->contact(), endpoint: Url::fromString('https://geomet.example/'), searchKilometers: 2)->forecast($this->montreal());

        self::assertSame('2026-11-13T09:00:00+00:00', $forecast->issuedAt->format(\DATE_ATOM));
        self::assertSame([[Condition::Unknown, 100, null], [Condition::Cloudy, 0, 'Cloudy']], array_map(static fn (ForecastPeriod $period): array => [$period->condition, $period->precipitationProbability, $period->summary], $forecast->periods));

        $this->expectException(WeatherUnavailable::class);
        $this->expectExceptionMessageIsOrContains('Environment Canada sent what its format does not describe: no time of issue');

        new CitypageWeatherProvider(new MockHttpClient(new MockResponse('{"features":[{"geometry":{"coordinates":[-73.58,45.5]},"properties":{}}]}')), $this->contact())->forecast($this->montreal());
    }

    public function testEnvironmentCanadaQueriesTheEndpointGiven(): void
    {
        new CitypageWeatherProvider($this->client('eccc-citypage.json'), $this->contact(), endpoint: Url::fromString('https://geomet.example/'))->forecast($this->montreal());

        self::assertStringStartsWith('https://geomet.example/collections/citypageweather-realtime/items?f=json&lang=en&', $this->requests[0][1]);
    }

    public function testTheSearchDistanceIsBounded(): void
    {
        foreach ([0.9, 200.1] as $kilometers) {
            try {
                new CitypageWeatherProvider(new MockHttpClient(), $this->contact(), searchKilometers: $kilometers);
                self::fail('A search distance of '.$kilometers.' km was taken.');
            } catch (InvalidArgument $exception) {
                self::assertSame('The search distance of a forecast site is between 1 and 200 km.', $exception->getMessage());
            }
        }

        self::assertTrue(new CitypageWeatherProvider(new MockHttpClient(), $this->contact(), searchKilometers: 200)->supports(new EventLocation(Coordinates::of(0, 0), 'CA')));
    }

    public function testEnvironmentCanadaAlertsAreTheOnesOverTheEvent(): void
    {
        $source = new WeatherAlertsSource($this->client('eccc-alerts.json'), $this->contact());
        $alerts = $source->alerts($this->montreal());

        self::assertStringStartsWith('https://api.weather.gc.ca/collections/weather-alerts/items?f=json&limit=500&bbox=-73.58', $this->requests[0][1]);
        self::assertTrue($source->supports(new EventLocation(Coordinates::of(45.5, -73.5), 'CA')));
        self::assertFalse($source->supports(new EventLocation(Coordinates::of(45.5, -73.5), 'FR')));
        self::assertEquals(Attribution::environmentCanada(), $source->attribution());
        self::assertSame(['freezing rain warning', 'special weather statement', 'rainfall warning', 'tornado warning'], array_map(static fn (WeatherAlert $alert): string => $alert->event, $alerts));

        [$freezing, $statement, $rainfall, $tornado] = $alerts;
        self::assertSame('FRW|2026-11-13T14:00:00Z|2026-11-13T20:00:00Z', $freezing->id);
        self::assertSame([AlertSeverity::Severe, 'Environment and Climate Change Canada', 'Freezing rain', 'Montréal', 'en', null, null], [$freezing->severity, $freezing->sender, $freezing->headline, $freezing->area, $freezing->language, $freezing->instruction, $freezing->web]);
        self::assertStringStartsWith('Freezing rain is expected this afternoon. Surfaces such as highways', (string) $freezing->description);
        self::assertSame(['2026-11-13T14:00:00+00:00', '2026-11-13T20:00:00+00:00'], [$freezing->effective?->format(\DATE_ATOM), $freezing->expires?->format(\DATE_ATOM)]);
        self::assertSame(['statement-1||2026-11-14T11:00:00Z', AlertSeverity::Unknown, null, null, '2026-11-14T11:00:00+00:00'], [$statement->id, $statement->severity, $statement->description, $statement->effective, $statement->expires?->format(\DATE_ATOM)]);
        self::assertSame([AlertSeverity::Moderate, 'RW|2026-11-13T15:00:00Z|'], [$rainfall->severity, $rainfall->id]);
        self::assertSame(AlertSeverity::Extreme, $tornado->severity);
    }

    public function testEnvironmentCanadaAlertsInFrench(): void
    {
        $alerts = new WeatherAlertsSource($this->client('eccc-alerts.json'), $this->contact(), 'fr', Url::fromString('https://geomet.example'))->alerts($this->montreal());

        self::assertStringStartsWith('https://geomet.example/collections/weather-alerts/items?', $this->requests[0][1]);
        self::assertSame(['avertissement de pluie verglaçante', 'seulement en français'], array_map(static fn (WeatherAlert $alert): string => $alert->event, $alerts));
        self::assertSame(['Pluie verglaçante', 'De la pluie verglaçante est prévue cet après-midi.', 'fr'], [$alerts[0]->headline, $alerts[0]->description, $alerts[0]->language]);
        self::assertSame('nameless||', $alerts[1]->id);
    }

    public function testAPolygonKnowsWhatItContains(): void
    {
        $square = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]]]];

        self::assertTrue(Polygon::contains($square, Coordinates::of(1, 1)));
        self::assertFalse(Polygon::contains($square, Coordinates::of(5, 5)));
        self::assertFalse(Polygon::contains($square, Coordinates::of(11, 5)));
        self::assertFalse(Polygon::contains($square, Coordinates::of(5, -1)));
        self::assertFalse(Polygon::contains(['type' => 'Point', 'coordinates' => [1, 1]], Coordinates::of(1, 1)));
        self::assertFalse(Polygon::contains(['type' => 'Polygon', 'coordinates' => [[[0, 0], ['x', 0], [10, 10], [0, 0]]]], Coordinates::of(1, 2)));
        self::assertFalse(Polygon::contains('nonsense', Coordinates::of(1, 1)));
        self::assertTrue(Polygon::contains(['type' => 'MultiPolygon', 'coordinates' => [[[[20, 20], [30, 20], [30, 30], [20, 20]]], $square['coordinates']]], Coordinates::of(9, 1)));
    }

    public function testTheClientReportsWhatWentWrong(): void
    {
        foreach ([
            [new MockResponse('{}', ['http_code' => 503]), 'MET Norway answered with the status 503.'],
            [new MockResponse('{}', ['http_code' => 304]), 'MET Norway answered with the status 304.'],
            [new MockResponse('nope'), 'MET Norway sent what its format does not describe: not JSON'],
            [new MockResponse('"text"'), 'MET Norway sent what its format does not describe: not a JSON object'],
            [new MockResponse('', ['error' => 'Connection refused']), 'MET Norway did not answer: Connection refused'],
            [new MockResponse(str_repeat('[', 70).str_repeat(']', 70)), 'MET Norway sent what its format does not describe: not JSON'],
        ] as [$response, $message]) {
            try {
                new JsonClient(new MockHttpClient($response), $this->contact(), 'MET Norway')->get('https://api.met.no/x');
                self::fail('Read: '.$message);
            } catch (WeatherUnavailable $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertSame(['a' => 1], new JsonClient(new MockHttpClient(new MockResponse('{"a":1}', ['http_code' => 203])), $this->contact(), 'MET Norway')->get('https://api.met.no/x'));
    }

    public function testTheClientStopsReadingPastItsBytes(): void
    {
        $client = new MockHttpClient(new MockResponse(['{"a":"', str_repeat('x', 40), '"}']));

        self::assertSame(['a' => str_repeat('x', 40)], new JsonClient(new MockHttpClient(new MockResponse('{"a":"'.str_repeat('x', 40).'"}')), $this->contact(), 'P', 48)->get('https://a.example/'));

        $this->expectException(WeatherUnavailable::class);
        $this->expectExceptionMessageIsOrContains('P sent more than 47 bytes.');

        new JsonClient($client, $this->contact(), 'P', 47)->get('https://a.example/');
    }

    public function testDataIsReadWithoutTrustingItsShape(): void
    {
        self::assertSame(3, Data::at(['a' => ['b' => 3]], 'a', 'b'));
        self::assertNull(Data::at(['a' => 3], 'a', 'b'));
        self::assertNull(Data::at(['a' => null], 'a', 'b'));
        self::assertSame([1], Data::list([1]));
        self::assertSame([], Data::list(['a' => 1]));
        self::assertSame([], Data::list('x'));
        self::assertSame(2.5, Data::float('2.5'));
        self::assertSame(2.0, Data::float(2));
        self::assertNull(Data::float('calm'));
        self::assertNull(Data::float(true));
        self::assertSame(3, Data::int(2.5));
        self::assertNull(Data::int(\INF));
        self::assertNull(Data::int('x'));
        self::assertSame('a', Data::string('a'));
        self::assertNull(Data::string('  '));
        self::assertNull(Data::string(3));
        self::assertSame('2026-11-13T17:00:00+00:00', Data::instant('2026-11-13T12:00:00-05:00')?->format(\DATE_ATOM));
        self::assertSame('2026-11-13T12:00:00+00:00', Data::instant('2026-11-13T12:00Z')?->format(\DATE_ATOM));
        self::assertSame('2026-11-13T07:03:31.061+00:00', Data::instant('2026-11-13T07:03:31.061Z')?->format('Y-m-d\TH:i:s.vP'));
        self::assertNull(Data::instant('2026-11-13T12:00:00'));
        self::assertNull(Data::instant('2026-11-13 12:00:00Z'));
        self::assertNull(Data::instant('2026-13-45T12:00:00Z'));
        self::assertNull(Data::instant("2026-11-13T12:00:00Z\n"));
        self::assertNull(Data::instant(20261113));
    }

    private function client(string $fixture): MockHttpClient
    {
        return new MockHttpClient(
            function (string $method, string $url, array $options) use ($fixture): MockResponse {
                $this->requests[] = [$method, $url, $options];

                return new MockResponse((string) file_get_contents(self::FIXTURES.$fixture), ['response_headers' => ['Content-Type' => 'application/json']]);
            }
        );
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<string>
     */
    private function headers(array $options): array
    {
        $headers = $options['headers'] ?? [];

        return \is_array($headers) ? array_values(array_filter($headers, is_string(...))) : [];
    }

    private function contact(): Contact
    {
        return new Contact('EventsApp/1.4', 'ops@events.example');
    }

    private function montreal(): EventLocation
    {
        return new EventLocation(Coordinates::of(45.5017, -73.5673), 'CA');
    }

    public function testAnInstantStaysAnInstant(): void
    {
        self::assertSame('2026-11-13T12:00:00+00:00', new DateTimeImmutable('2026-11-13T07:00:00-05:00')->setTimezone(new DateTimeZone('UTC'))->format(\DATE_ATOM));
    }
}
