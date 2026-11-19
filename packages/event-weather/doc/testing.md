# Testing

## The library's tests

The suite `tests/Unit` needs no network. The adapters are tested against Symfony's `MockHttpClient` with answers
written for the tests in `tests/Fixtures`, from the formats the providers document: a Locationforecast `complete`
answer, a `citypageweather-realtime` collection with two sites, a `weather-alerts` collection with polygons, ended and
duplicated alerts, and a CAP 1.2 alert in English and French. The tests check the requests sent — address, query,
`User-Agent`, bounds —, every field read, the choice of the provider and its availability with a `MockClock`, the
caching through `CachingHttpClient`, and every refusal of the CAP reader.

From the monorepo root:

```sh
vendor/bin/phpunit packages/event-weather/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz target `cap` gives the CAP reader documents nobody wrote, from the seed of `tests/Fuzz/corpus/cap`: whatever it
reads, it returns alerts or throws `InvalidCapDocument`.

```sh
composer fuzz -- cap
```

## Testing code that shows forecasts

`EventWeather` takes its providers and its clock: give it a provider of the test's own and a `MockClock`, and compare
the `EventForecast`.

```php
use Symfony\Component\Clock\MockClock;

$weather = new EventWeather([new FixedForecast($periods)], new MockClock('2026-11-13 12:00:00 UTC'));
$forecast = $weather->forecastFor($location, $start, $end);

self::assertSame(Availability::Available, $forecast->availability);
```

To test an adapter's wiring, give it a `MockHttpClient` and an answer saved from the provider.
