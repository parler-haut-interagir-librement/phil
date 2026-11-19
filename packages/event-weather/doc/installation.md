# Installation

## Requirements

PHP 8.5 or later, with `ext-dom`, `ext-libxml` and `ext-mbstring` for the CAP reader. The library requires the Symfony
HTTP client contracts, `psr/clock`, `psr/log`, `phil/geo` and `phil/url`; an application installs an HTTP client, such
as `symfony/http-client`, a cache, such as `symfony/cache`, and a clock, such as `symfony/clock`.

## Installing

```sh
composer require phil/event-weather symfony/http-client symfony/cache symfony/clock
```

## The cache

MET Norway's terms forbid asking again before the `Expires` of an answer, and ask for `If-Modified-Since` after it.
Environment Canada's answers carry the same headers. The providers send their requests through the HTTP client they are
given: wrap it in Symfony's `CachingHttpClient`, which keeps each answer until it expires and revalidates it with the
`Last-Modified` it carried. Give it a tag-aware cache that lasts between requests — a filesystem, Redis or database
adapter — and `sharedCache: false`, the answers being for the application alone.

```php
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new CachingHttpClient(HttpClient::create(), new FilesystemTagAwareAdapter('weather'), sharedCache: false);
```

MET Norway's coordinates are cut to four decimals, so events a few metres apart share an answer. The cache is the
application's choice: the library keeps nothing between calls.

## The identification

Both providers ask who sends the requests. A `Contact` gives the application's name, as a `User-Agent` product with an
optional version, and a way to reach whoever runs it — a web address or an email address:

```php
use Phil\EventWeather\Contact;

$contact = new Contact('EventsApp/1.4', 'https://events.example/contact');
// User-Agent: EventsApp/1.4 https://events.example/contact
```

MET Norway bans clients whose identification is made up or copied: each application gives its own name and contact.

## As Symfony services

```php
// config/services.php
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EnvironmentCanada\WeatherAlertsSource;
use Phil\EventWeather\EventWeather;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Symfony\Component\HttpClient\CachingHttpClient;

$services->set('weather.http_client', CachingHttpClient::class)
    ->args([service('http_client'), service('cache.weather'), '$sharedCache' => false]);
$services->set(Contact::class)->args(['EventsApp/1.4', 'https://events.example/contact']);
$services->set(CitypageWeatherProvider::class)->args([service('weather.http_client'), service(Contact::class), 'fr']);
$services->set(LocationforecastProvider::class)->args([service('weather.http_client'), service(Contact::class)]);
$services->set(WeatherAlertsSource::class)->args([service('weather.http_client'), service(Contact::class), 'fr']);
$services->set(EventWeather::class)->args([
    [service(CitypageWeatherProvider::class), service(LocationforecastProvider::class)],
    service('clock'),
    [service(WeatherAlertsSource::class)],
    service('logger'),
]);
```

`cache.weather` is a tag-aware cache pool of the application. Every class of the library is immutable and keeps no state
between calls: one `EventWeather` serves every request of a long-running worker.
