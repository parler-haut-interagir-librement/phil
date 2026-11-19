<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit;

use DateTimeImmutable;
use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Availability;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contract\AlertSource;
use Phil\EventWeather\Contract\ForecastProvider;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\EventWeather;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\Forecast;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\WeatherAlert;
use Phil\Geo\Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Throwable;

#[CoversClass(EventWeather::class)]
final class EventWeatherTest extends TestCase
{
    public const string NOW = '2026-11-13T12:00:00Z';

    public function testTheFirstProviderThatReachesTheEventWins(): void
    {
        $canada = $this->provider('Canada', 24, ['CA'], ['2026-11-13T13:00:00Z', '2026-11-13T14:00:00Z', '2026-11-13T15:00:00Z']);
        $world = $this->provider('World', 216, null, ['2026-11-13T13:00:00Z']);
        $weather = new EventWeather([$canada, $world], new MockClock(self::NOW), [$this->alerts('Canada', ['CA'])]);

        $forecast = $weather->forecastFor($this->location('CA'), new DateTimeImmutable('2026-11-13T09:00:00-05:00'), new DateTimeImmutable('2026-11-13T10:30:00-05:00'));

        self::assertSame(Availability::Available, $forecast->availability);
        self::assertSame(['2026-11-13T14:00:00+00:00', '2026-11-13T15:30:00+00:00'], [$forecast->start->format(\DATE_ATOM), $forecast->end->format(\DATE_ATOM)]);
        self::assertSame(['2026-11-13T14:00:00+00:00', '2026-11-13T15:00:00+00:00'], array_map(static fn (ForecastPeriod $period): string => $period->from->format(\DATE_ATOM), $forecast->periods));
        self::assertSame('Canada', $forecast->forecast?->attribution->provider);
        self::assertSame(['during'], array_map(static fn (WeatherAlert $alert): string => $alert->id, $forecast->alerts));
        self::assertSame(['Canada'], array_map(static fn (Attribution $attribution): string => $attribution->provider, $forecast->attributions));
    }

    public function testBeyondTheHorizonOfTheFirstTheNextTakesOver(): void
    {
        $weather = new EventWeather(
            [$this->provider('Canada', 24, ['CA'], ['2026-11-13T13:00:00Z']), $this->provider('World', 216, null, ['2026-11-15T12:00:00Z'])],
            new MockClock(self::NOW),
            [$this->alerts('Canada', ['CA'])],
        );

        $forecast = $weather->forecastFor($this->location('CA'), new DateTimeImmutable('2026-11-15T12:00:00Z'), new DateTimeImmutable('2026-11-15T14:00:00Z'));

        self::assertSame(Availability::Available, $forecast->availability);
        self::assertSame('World', $forecast->forecast?->attribution->provider);
        self::assertSame(['late'], array_map(static fn (WeatherAlert $alert): string => $alert->id, $forecast->alerts));
        self::assertSame(['World', 'Canada'], array_map(static fn (Attribution $attribution): string => $attribution->provider, $forecast->attributions));
    }

    public function testTheHorizonEndsBeforeItsLastHour(): void
    {
        $weather = new EventWeather([$this->provider('World', 24, null, ['2026-11-14T11:00:00Z', '2026-11-14T12:00:00Z'])], new MockClock(self::NOW));

        self::assertSame(Availability::Available, $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-14T11:59:59Z'), new DateTimeImmutable('2026-11-14T13:00:00Z'))->availability);
        self::assertSame(Availability::TooFar, $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-14T12:00:00Z'), new DateTimeImmutable('2026-11-14T13:00:00Z'))->availability);
    }

    public function testAPastEventHasNoForecast(): void
    {
        $provider = $this->provider('World', 216, null, [], fail: true);
        $weather = new EventWeather([$provider], new MockClock(self::NOW));

        self::assertSame(Availability::Past, $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-13T10:00:00Z'), new DateTimeImmutable(self::NOW))->availability);
        self::assertSame(Availability::Available, new EventWeather([$this->provider('World', 216, null, ['2026-11-13T11:00:00Z'])], new MockClock(self::NOW))->forecastFor($this->location(), new DateTimeImmutable('2026-11-13T10:00:00Z'), new DateTimeImmutable('2026-11-13T12:00:01Z'))->availability);
    }

    public function testAPlaceNoProviderServesIsUnsupported(): void
    {
        $weather = new EventWeather([$this->provider('Canada', 24, ['CA'], [])], new MockClock(self::NOW), [$this->alerts('Canada', ['CA'])]);
        $forecast = $weather->forecastFor($this->location('FR'), new DateTimeImmutable(self::NOW), new DateTimeImmutable('2026-11-13T13:00:00Z'));

        self::assertSame(Availability::Unsupported, $forecast->availability);
        self::assertSame([[], [], []], [$forecast->periods, $forecast->alerts, $forecast->attributions]);
        self::assertSame(Availability::Unsupported, new EventWeather([], new MockClock(self::NOW))->forecastFor($this->location(), new DateTimeImmutable(self::NOW), new DateTimeImmutable(self::NOW.'')->modify('+1 hour'))->availability);
    }

    public function testAFailingProviderIsLoggedAndTheNextTried(): void
    {
        $logger = $this->logger();
        $weather = new EventWeather(
            [$this->provider('Broken', 216, null, [], fail: true), $this->provider('World', 216, null, ['2026-11-13T13:00:00Z'])],
            new MockClock(self::NOW),
            logger: $logger,
        );

        $forecast = $weather->forecastFor($this->location(), new DateTimeImmutable(self::NOW), new DateTimeImmutable('2026-11-13T14:00:00Z'));

        self::assertSame('World', $forecast->forecast?->attribution->provider);
        self::assertSame(['warning: The forecast of {provider} is unavailable: {message} Broken Broken is down. Broken is down.'], $logger->lines);
    }

    public function testAProviderThatDoesNotReachTheEventIsNotAsked(): void
    {
        $weather = new EventWeather(
            [$this->provider('Short', 1, null, ['2026-11-15T12:00:00Z']), $this->provider('Long', 216, null, ['2026-11-15T12:00:00Z'])],
            new MockClock(self::NOW),
        );

        $forecast = $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-15T12:00:00Z'), new DateTimeImmutable('2026-11-15T13:00:00Z'));

        self::assertSame('Long', $forecast->forecast?->attribution->provider);
    }

    public function testWhenNoProviderAnswersTheForecastIsUnavailable(): void
    {
        $weather = new EventWeather([$this->provider('Broken', 216, null, [], fail: true)], new MockClock(self::NOW), [$this->alerts('Canada', ['CA'])]);
        $forecast = $weather->forecastFor($this->location('CA'), new DateTimeImmutable(self::NOW), new DateTimeImmutable('2026-11-13T18:00:00Z'));

        self::assertSame(Availability::Unavailable, $forecast->availability);
        self::assertNull($forecast->forecast);
        self::assertSame(['during'], array_map(static fn (WeatherAlert $alert): string => $alert->id, $forecast->alerts));
        self::assertSame(['Canada'], array_map(static fn (Attribution $attribution): string => $attribution->provider, $forecast->attributions));
    }

    public function testAnAnswerWithoutTheEventsTimeIsTooFar(): void
    {
        $weather = new EventWeather([$this->provider('World', 216, null, ['2026-11-13T13:00:00Z'])], new MockClock(self::NOW));
        $forecast = $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-16T12:00:00Z'), new DateTimeImmutable('2026-11-16T13:00:00Z'));

        self::assertSame(Availability::TooFar, $forecast->availability);
        self::assertSame([], $forecast->attributions);
    }

    public function testFailingAlertsAreLoggedAndLeftOut(): void
    {
        $logger = $this->logger();
        $weather = new EventWeather(
            [$this->provider('World', 216, null, ['2026-11-13T13:00:00Z'])],
            new MockClock(self::NOW),
            [$this->alerts('Elsewhere', ['FR']), $this->alerts('Canada', ['CA'], fail: true), $this->alerts('Second', ['CA'])],
            $logger,
        );

        $forecast = $weather->forecastFor($this->location('CA'), new DateTimeImmutable(self::NOW), new DateTimeImmutable('2026-11-13T14:00:00Z'));

        self::assertSame([], $forecast->alerts);
        self::assertSame(['World'], array_map(static fn (Attribution $attribution): string => $attribution->provider, $forecast->attributions));
        self::assertSame(['warning: The alerts of {provider} are unavailable: {message} Canada Canada is down. Canada is down.'], $logger->lines);
    }

    public function testNoAlertNoAlertAttribution(): void
    {
        $weather = new EventWeather([$this->provider('World', 216, null, ['2026-11-13T13:00:00Z'])], new MockClock(self::NOW), [$this->alerts('Canada', ['CA'])]);
        $forecast = $weather->forecastFor($this->location('CA'), new DateTimeImmutable('2026-11-13T12:30:00Z'), new DateTimeImmutable('2026-11-13T13:30:00Z'));

        self::assertSame([], $forecast->alerts);
        self::assertSame(['World'], array_map(static fn (Attribution $attribution): string => $attribution->provider, $forecast->attributions));
    }

    public function testAnEventEndsAfterItStarts(): void
    {
        $weather = new EventWeather([], new MockClock(self::NOW));

        self::assertSame(Availability::Unsupported, $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-14T12:00:00Z'), new DateTimeImmutable('2026-11-14T07:00:00-05:00'))->availability);

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('An event ends after it starts.');

        $weather->forecastFor($this->location(), new DateTimeImmutable('2026-11-14T12:00:00Z'), new DateTimeImmutable('2026-11-14T06:59:59-05:00'));
    }

    private function location(?string $country = null): EventLocation
    {
        return new EventLocation(Coordinates::of(45.5, -73.5), $country);
    }

    /**
     * @param ?list<string> $countries null for everywhere
     * @param list<string>  $hours     the start of each hourly period
     */
    private function provider(string $name, int $horizon, ?array $countries, array $hours, bool $fail = false): ForecastProvider
    {
        return new readonly class($name, $horizon, $countries, $hours, $fail) implements ForecastProvider {
            /**
             * @param ?list<string> $countries
             * @param list<string>  $hours
             */
            public function __construct(private string $name, private int $horizon, private ?array $countries, private array $hours, private bool $fail)
            {
            }

            public function attribution(): Attribution
            {
                $base = Attribution::metNorway();

                return new Attribution($this->name, $this->name, $base->licence, $base->licenceUrl, $base->source);
            }

            public function supports(EventLocation $location): bool
            {
                return null === $this->countries || \in_array($location->country, $this->countries, true);
            }

            public function horizonHours(): int
            {
                return max(1, $this->horizon);
            }

            public function forecast(EventLocation $location): Forecast
            {
                if ($this->fail) {
                    throw new WeatherUnavailable($this->name.' is down.');
                }

                return new Forecast($this->attribution(), new DateTimeImmutable(EventWeatherTest::NOW), array_map(
                    static fn (string $hour): ForecastPeriod => new ForecastPeriod(new DateTimeImmutable($hour), new DateTimeImmutable($hour)->modify('+1 hour'), Condition::Clear),
                    $this->hours,
                ));
            }
        };
    }

    /**
     * @param list<string> $countries
     */
    private function alerts(string $name, array $countries, bool $fail = false): AlertSource
    {
        return new readonly class($name, $countries, $fail) implements AlertSource {
            /**
             * @param list<string> $countries
             */
            public function __construct(private string $name, private array $countries, private bool $fail)
            {
            }

            public function attribution(): Attribution
            {
                $base = Attribution::environmentCanada();

                return new Attribution($this->name, $this->name, $base->licence, $base->licenceUrl, $base->source);
            }

            public function supports(EventLocation $location): bool
            {
                return \in_array($location->country, $this->countries, true);
            }

            public function alerts(EventLocation $location): array
            {
                if ($this->fail) {
                    throw new WeatherUnavailable($this->name.' is down.');
                }

                return [
                    new WeatherAlert('during', 'warning', AlertSeverity::Severe, $this->name, new DateTimeImmutable('2026-11-13T14:30:00Z'), new DateTimeImmutable('2026-11-13T16:00:00Z')),
                    new WeatherAlert('late', 'watch', AlertSeverity::Moderate, $this->name, new DateTimeImmutable('2026-11-15T13:00:00Z')),
                ];
            }
        };
    }

    /**
     * @return AbstractLogger&object{lines: list<string>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $lines = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $exception = $context['exception'] ?? null;
                $this->lines[] = \sprintf('%s: %s %s %s %s', \is_string($level) ? $level : '', $message, \is_string($context['provider'] ?? null) ? $context['provider'] : '', \is_string($context['message'] ?? null) ? $context['message'] : '', $exception instanceof Throwable ? $exception->getMessage() : '');
            }
        };
    }
}
