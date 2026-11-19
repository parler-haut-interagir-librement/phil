<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit;

use DateTimeImmutable;
use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Availability;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contact;
use Phil\EventWeather\EventForecast;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Forecast;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\WeatherAlert;
use Phil\Geo\Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Contact::class)]
#[CoversClass(EventLocation::class)]
#[CoversClass(Attribution::class)]
#[CoversClass(Condition::class)]
#[CoversClass(ForecastPeriod::class)]
#[CoversClass(AlertSeverity::class)]
#[CoversClass(WeatherAlert::class)]
#[CoversClass(Forecast::class)]
#[CoversClass(EventForecast::class)]
final class ModelTest extends TestCase
{
    public function testAContactIdentifiesTheApplication(): void
    {
        self::assertSame('EventsApp/1.4 ops@events.example', new Contact('EventsApp/1.4', 'ops@events.example')->userAgent());
        self::assertSame('events-app https://events.example/contact', new Contact('events-app', 'https://events.example/contact')->userAgent());
        self::assertSame('A/1.0+build', new Contact('A/1.0+build', 'a@b.example')->application);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidContacts(): iterable
    {
        yield 'empty name' => ['', 'a@b.example', 'The application is named'];
        yield 'name with a space' => ['Events App', 'a@b.example', 'The application is named'];
        yield 'name with a line feed' => ["App\n", 'a@b.example', 'The application is named'];
        yield 'two versions' => ['App/1/2', 'a@b.example', 'The application is named'];
        yield 'leading dot' => ['.App', 'a@b.example', 'The application is named'];
        yield 'no contact' => ['App', '', 'The contact is an http or https address or an email address.'];
        yield 'not an address' => ['App', 'someone', 'The contact is'];
        yield 'local address' => ['App', 'http://localhost/', 'The contact is'];
        yield 'header injection' => ['App', "a@b.example\r\nX-Evil: 1", 'The contact is'];
        yield 'mail without a dot' => ['App', 'a@b', 'The contact is'];
        yield 'mail trailing line feed' => ['App', "a@b.example\n", 'The contact is'];
        yield 'url with a tab' => ['App', "https://events.example/\t", 'The contact is'];
    }

    #[DataProvider('invalidContacts')]
    public function testAContactIsChecked(string $application, string $contact, string $message): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Contact($application, $contact);
    }

    public function testALocationCarriesItsCountry(): void
    {
        $coordinates = Coordinates::of(45.5, -73.5);
        $location = new EventLocation($coordinates, 'ca');

        self::assertSame($coordinates, $location->coordinates);
        self::assertSame('CA', $location->country);
        self::assertTrue($location->isIn('CA'));
        self::assertTrue($location->isIn('ca'));
        self::assertFalse($location->isIn('US'));
        self::assertNull(new EventLocation($coordinates)->country);
        self::assertFalse(new EventLocation($coordinates)->isIn('CA'));

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('The country is an ISO 3166-1 code of two letters, such as "CA".');

        new EventLocation($coordinates, 'CAN');
    }

    public function testTheAttributionsNameTheLicences(): void
    {
        $canada = Attribution::environmentCanada();
        $norway = Attribution::metNorway();

        self::assertSame(['Environment and Climate Change Canada', 'Data Source: Environment and Climate Change Canada', 'Environment and Climate Change Canada Data Servers End-use Licence', 'https://eccc-msc.github.io/open-data/licence/readme_en/', 'https://weather.gc.ca/'], [$canada->provider, $canada->text, $canada->licence, $canada->licenceUrl->toString(), $canada->source->toString()]);
        self::assertSame(['MET Norway', 'Data from MET Norway', 'CC BY 4.0 and NLOD 2.0', 'https://creativecommons.org/licenses/by/4.0/', 'https://api.met.no/'], [$norway->provider, $norway->text, $norway->licence, $norway->licenceUrl->toString(), $norway->source->toString()]);
    }

    /**
     * @return iterable<string, array{string, Condition}>
     */
    public static function words(): iterable
    {
        yield 'thunder' => ['Risk of thunderstorms', Condition::Thunderstorm];
        yield 'freezing' => ['Periods of freezing rain', Condition::FreezingRain];
        yield 'ice pellets' => ['Ice pellets', Condition::Sleet];
        yield 'sleet' => ['Sleet', Condition::Sleet];
        yield 'rain and snow' => ['Rain and snow', Condition::Sleet];
        yield 'snow and rain' => ['Snow and rain', Condition::Sleet];
        yield 'wet snow' => ['Wet snow', Condition::Sleet];
        yield 'snow' => ['Snow', Condition::Snow];
        yield 'flurries' => ['Chance of flurries', Condition::Snow];
        yield 'blizzard' => ['Blizzard', Condition::Snow];
        yield 'showers' => ['A few showers', Condition::Showers];
        yield 'rain' => ['Rain', Condition::Rain];
        yield 'drizzle' => ['Drizzle', Condition::Rain];
        yield 'fog' => ['Fog patches', Condition::Fog];
        yield 'mist' => ['Mist', Condition::Fog];
        yield 'haze' => ['Haze', Condition::Fog];
        yield 'partly' => ['Partly cloudy', Condition::PartlyCloudy];
        yield 'mix' => ['A mix of sun and cloud', Condition::PartlyCloudy];
        yield 'few clouds' => ['A few clouds', Condition::PartlyCloudy];
        yield 'cloudy' => ['Mostly cloudy', Condition::Cloudy];
        yield 'overcast' => ['Overcast', Condition::Cloudy];
        yield 'sunny' => ['Mainly sunny', Condition::Clear];
        yield 'clear' => ['Clear', Condition::Clear];
        yield 'unknown' => ['Not available', Condition::Unknown];
    }

    #[DataProvider('words')]
    public function testItReadsTheWordsOfEnvironmentCanada(string $words, Condition $condition): void
    {
        self::assertSame($condition, Condition::fromWords($words));
    }

    /**
     * @return iterable<string, array{string, Condition}>
     */
    public static function symbols(): iterable
    {
        yield 'thunder' => ['heavyrainshowersandthunder_day', Condition::Thunderstorm];
        yield 'sleet' => ['lightsleetshowers_night', Condition::Sleet];
        yield 'snow' => ['heavysnow', Condition::Snow];
        yield 'showers' => ['rainshowers_polartwilight', Condition::Showers];
        yield 'rain' => ['lightrain', Condition::Rain];
        yield 'fog' => ['fog', Condition::Fog];
        yield 'cloudy' => ['cloudy', Condition::Cloudy];
        yield 'partly cloudy' => ['partlycloudy_day', Condition::PartlyCloudy];
        yield 'fair' => ['fair_night', Condition::PartlyCloudy];
        yield 'clear' => ['clearsky_day', Condition::Clear];
        yield 'fog by day' => ['fog_day', Condition::Fog];
        yield 'suffix in the middle' => ['clearsky_dayx', Condition::Unknown];
        yield 'unknown' => ['volcano', Condition::Unknown];
    }

    #[DataProvider('symbols')]
    public function testItReadsTheSymbolsOfMetNorway(string $symbol, Condition $condition): void
    {
        self::assertSame($condition, Condition::fromMetSymbol($symbol));
    }

    public function testItSaysWhichConditionsAreWet(): void
    {
        self::assertSame(
            ['rain', 'showers', 'thunderstorm', 'freezing-rain', 'sleet', 'snow'],
            array_values(array_map(static fn (Condition $condition): string => $condition->value, array_filter(Condition::cases(), static fn (Condition $condition): bool => $condition->isWet()))),
        );
    }

    public function testAPeriodIsInUtc(): void
    {
        $period = new ForecastPeriod(new DateTimeImmutable('2026-11-13T07:00:00-05:00'), new DateTimeImmutable('2026-11-13T08:00:00-05:00'), Condition::Rain, 1.5, 0, 0.0, 10.0, 20.0, 359, -1.0, 3.0, 'Rain');

        self::assertSame(['2026-11-13T12:00:00+00:00', '2026-11-13T13:00:00+00:00'], [$period->from->format(\DATE_ATOM), $period->to->format(\DATE_ATOM)]);
        self::assertSame([Condition::Rain, 1.5, 0, 0.0, 10.0, 20.0, 359, -1.0, 3.0, 'Rain'], [$period->condition, $period->temperature, $period->precipitationProbability, $period->precipitation, $period->windSpeed, $period->windGust, $period->windDirection, $period->minTemperature, $period->maxTemperature, $period->summary]);
        self::assertTrue($period->overlaps(new DateTimeImmutable('2026-11-13T12:59:59Z'), new DateTimeImmutable('2026-11-13T14:00:00Z')));
        self::assertFalse($period->overlaps(new DateTimeImmutable('2026-11-13T13:00:00Z'), new DateTimeImmutable('2026-11-13T14:00:00Z')));
        self::assertTrue($period->overlaps(new DateTimeImmutable('2026-11-13T11:00:00Z'), new DateTimeImmutable('2026-11-13T12:00:01Z')));
        self::assertFalse($period->overlaps(new DateTimeImmutable('2026-11-13T11:00:00Z'), new DateTimeImmutable('2026-11-13T12:00:00Z')));
        self::assertSame(100, new ForecastPeriod($period->from, $period->from, Condition::Clear, precipitationProbability: 100, windDirection: 0)->precipitationProbability);
    }

    /**
     * @return iterable<string, array{string, ?int, ?int, string}>
     */
    public static function invalidPeriods(): iterable
    {
        yield 'reversed' => ['2026-11-13T11:59:59Z', null, null, 'A forecast period ends after it starts.'];
        yield 'negative chance' => ['2026-11-13T13:00:00Z', -1, null, 'A chance of precipitation is between 0 and 100 percent.'];
        yield 'chance past 100' => ['2026-11-13T13:00:00Z', 101, null, 'A chance of precipitation is between 0 and 100 percent.'];
        yield 'negative direction' => ['2026-11-13T13:00:00Z', null, -1, 'A wind direction is between 0 and 359 degrees.'];
        yield 'full circle' => ['2026-11-13T13:00:00Z', null, 360, 'A wind direction is between 0 and 359 degrees.'];
    }

    #[DataProvider('invalidPeriods')]
    public function testAPeriodIsChecked(string $to, ?int $chance, ?int $direction, string $message): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ForecastPeriod(new DateTimeImmutable('2026-11-13T12:00:00Z'), new DateTimeImmutable($to), Condition::Clear, precipitationProbability: $chance, windDirection: $direction);
    }

    public function testSeveritiesAreOrdered(): void
    {
        self::assertTrue(AlertSeverity::Extreme->isAtLeast(AlertSeverity::Severe));
        self::assertTrue(AlertSeverity::Severe->isAtLeast(AlertSeverity::Severe));
        self::assertFalse(AlertSeverity::Minor->isAtLeast(AlertSeverity::Moderate));
        self::assertTrue(AlertSeverity::Minor->isAtLeast(AlertSeverity::Unknown));
        self::assertFalse(AlertSeverity::Unknown->isAtLeast(AlertSeverity::Minor));
    }

    public function testAnAlertIsInEffectDuringATime(): void
    {
        $alert = new WeatherAlert('id', 'warning', AlertSeverity::Severe, 'sender', new DateTimeImmutable('2026-11-13T09:00:00-05:00'), new DateTimeImmutable('2026-11-13T15:00:00-05:00'));
        $start = new DateTimeImmutable('2026-11-13T20:00:00Z');

        self::assertSame(['2026-11-13T14:00:00+00:00', '2026-11-13T20:00:00+00:00'], [$alert->effective?->format(\DATE_ATOM), $alert->expires?->format(\DATE_ATOM)]);
        self::assertFalse($alert->isInEffectDuring($start, new DateTimeImmutable('2026-11-13T21:00:00Z')));
        self::assertTrue($alert->isInEffectDuring(new DateTimeImmutable('2026-11-13T19:59:59Z'), $start));
        self::assertFalse($alert->isInEffectDuring(new DateTimeImmutable('2026-11-13T13:00:00Z'), new DateTimeImmutable('2026-11-13T14:00:00Z')));
        self::assertTrue($alert->isInEffectDuring(new DateTimeImmutable('2026-11-13T13:00:00Z'), new DateTimeImmutable('2026-11-13T14:00:01Z')));

        $open = new WeatherAlert('id', 'statement', AlertSeverity::Unknown, 'sender');
        self::assertTrue($open->isInEffectDuring($start, $start));
        self::assertNull($open->effective);
        self::assertNull($open->expires);
    }

    public function testAForecastKeepsTheIssueInUtcAndFiltersItsPeriods(): void
    {
        $one = new ForecastPeriod(new DateTimeImmutable('2026-11-13T12:00:00Z'), new DateTimeImmutable('2026-11-13T13:00:00Z'), Condition::Clear);
        $two = new ForecastPeriod(new DateTimeImmutable('2026-11-13T13:00:00Z'), new DateTimeImmutable('2026-11-13T14:00:00Z'), Condition::Rain);
        $forecast = new Forecast(Attribution::metNorway(), new DateTimeImmutable('2026-11-13T07:00:00-05:00'), [$one, $two]);

        self::assertSame('2026-11-13T12:00:00+00:00', $forecast->issuedAt->format(\DATE_ATOM));
        self::assertSame([$two], $forecast->between(new DateTimeImmutable('2026-11-13T13:00:00Z'), new DateTimeImmutable('2026-11-13T18:00:00Z')));
        self::assertSame([$one, $two], $forecast->between(new DateTimeImmutable('2026-11-13T12:30:00Z'), new DateTimeImmutable('2026-11-13T13:30:00Z')));
    }

    public function testAnEventForecastSumsUpItsPeriodsAndAlerts(): void
    {
        $start = new DateTimeImmutable('2026-11-13T12:00:00Z');
        $periods = [
            new ForecastPeriod($start, $start, Condition::Clear, precipitationProbability: 20),
            new ForecastPeriod($start, $start, Condition::Clear),
            new ForecastPeriod($start, $start, Condition::Rain, precipitationProbability: 70),
            new ForecastPeriod($start, $start, Condition::Rain, precipitationProbability: 0),
        ];
        $moderate = new WeatherAlert('a', 'a', AlertSeverity::Moderate, 's');
        $severe = new WeatherAlert('b', 'b', AlertSeverity::Severe, 's');
        $otherSevere = new WeatherAlert('c', 'c', AlertSeverity::Severe, 's');
        $forecast = new EventForecast(Availability::Available, $start, $start, null, $periods, [$moderate, $severe, $otherSevere, new WeatherAlert('d', 'd', AlertSeverity::Minor, 's')]);

        self::assertTrue($forecast->isAvailable());
        self::assertSame(70, $forecast->maxPrecipitationProbability());
        self::assertSame($severe, $forecast->mostSevereAlert());

        $none = new EventForecast(Availability::TooFar, $start, $start);
        self::assertFalse($none->isAvailable());
        self::assertNull($none->maxPrecipitationProbability());
        self::assertNull($none->mostSevereAlert());
        self::assertSame([[], [], [], null], [$none->periods, $none->alerts, $none->attributions, $none->forecast]);
        self::assertSame(0, new EventForecast(Availability::Available, $start, $start, periods: [$periods[3]])->maxPrecipitationProbability());
    }
}
