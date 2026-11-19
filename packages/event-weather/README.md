# phil/event-weather

**Show the forecast and the weather alerts of an outdoor event on its page, from the weather service of its place.**

phil/event-weather gives the forecast of an event's place and time: the periods of the event, in UTC, with their
condition, temperatures, chance and amount of precipitation and wind, the watches and warnings in effect during it,
and the attribution each provider's licence requires. Environment and Climate Change Canada serves Canadian places,
with its alerts; MET Norway serves every place, and takes over beyond Environment Canada's horizon. A forecast is shown
only within a provider's horizon: an event further ahead is too far to forecast.

It is for any PHP application that lists outdoor events — a festival, a market, a game day in a park — and wants their
page to say whether to bring an umbrella.

An alert is never summarized nor machine-translated: a page shows its text as issued, in the language the source gives,
as Environment and Climate Change Canada's licence requires — see [Showing alerts](doc/licences.md#showing-alerts).

It is a plain PHP library on the HTTP client contracts of Symfony, a PSR-20 clock and a PSR-3 logger, with `phil/geo`
and `phil/url`. The application gives it an HTTP client wrapped in Symfony's `CachingHttpClient`, which honours the
providers' `Expires` and revalidates with `If-Modified-Since`, and its own identification for the `User-Agent`. It also
reads Common Alerting Protocol 1.2 alerts.

The design: `EventWeather` tries the `Contract\ForecastProvider`s in order and asks the first `Contract\AlertSource` of
the place, and returns an `EventForecast` — its `Availability`, `ForecastPeriod`s, `WeatherAlert`s and `Attribution`s;
`EnvironmentCanada\CitypageWeatherProvider`, `EnvironmentCanada\WeatherAlertsSource` and
`MetNorway\LocationforecastProvider` are the adapters; `Cap\CapReader` reads CAP documents.

## Status

| Part | State |
|---|---|
| The forecast of an event: provider by place and horizon, periods of the event, alerts during it, attributions | Implemented and tested |
| Environment Canada: hourly city page forecasts of the nearest site, from MSC GeoMet | Implemented and tested |
| Environment Canada: public weather alerts whose area contains the event, from MSC GeoMet | Implemented and tested |
| MET Norway: Locationforecast 2.0, hourly then six-hourly, nine days ahead | Implemented and tested |
| Common Alerting Protocol 1.2 alerts, by language | Implemented and tested |
| Caching that honours `Expires` and `If-Modified-Since`, through Symfony's `CachingHttpClient` | Implemented and tested |
| Environment Canada's daily forecasts, beyond its hourly horizon | Not started: MET Norway covers those days |
| MET Norway's alerts (MetAlerts) for Norway | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the cache, the identification, the services. |
| [Forecasts](doc/forecasts.md) | How a provider is chosen, the availability of a forecast, periods and alerts. |
| [Providers](doc/providers.md) | Environment Canada and MET Norway: what each serves, how it is asked, what is read. |
| [Licences and attribution](doc/licences.md) | The licences and terms of each provider, the attribution to show, and how a page shows an alert. |
| [CAP alerts](doc/cap.md) | Reading Common Alerting Protocol 1.2 documents. |
| [Security](doc/security.md) | The requests sent, the answers read, and what stays with the application. |
| [Extension points](doc/extension-points.md) | The ports an application implements or replaces. |
| [Testing](doc/testing.md) | The library's tests and fuzz target, and testing code that shows forecasts. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/event-weather symfony/http-client symfony/cache symfony/clock
```

```php
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EnvironmentCanada\WeatherAlertsSource;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\EventWeather;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Phil\Geo\Coordinates;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new CachingHttpClient(HttpClient::create(), new FilesystemTagAwareAdapter('weather'), sharedCache: false);
$contact = new Contact('EventsApp/1.4', 'https://events.example/contact');

$weather = new EventWeather(
    [new CitypageWeatherProvider($client, $contact, 'fr'), new LocationforecastProvider($client, $contact)],
    new NativeClock(),
    [new WeatherAlertsSource($client, $contact, 'fr')],
);

$forecast = $weather->forecastFor(
    new EventLocation(Coordinates::of(45.5017, -73.5673), 'CA'),
    new DateTimeImmutable('2026-11-14 13:00', new DateTimeZone('America/Toronto')),
    new DateTimeImmutable('2026-11-14 17:00', new DateTimeZone('America/Toronto')),
);

if ($forecast->isAvailable()) {
    // $forecast->periods, $forecast->alerts, and $forecast->attributions beside them.
}
```

## Requirements

PHP 8.5 or later, with `ext-dom` and `ext-mbstring`, and an implementation of the Symfony HTTP client contracts.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
