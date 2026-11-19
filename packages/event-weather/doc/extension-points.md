# Extension points

The library is plain PHP and declares no service. What an application chooses goes through two ports and the
arguments of `EventWeather` and of the adapters.

## `Contract\ForecastProvider`

| Method | Contract |
|---|---|
| `attribution(): Attribution` | The credit line, licence and source its data requires |
| `supports(EventLocation): bool` | Whether the service forecasts for the place |
| `horizonHours(): int` | How many hours ahead of now its forecast reaches |
| `forecast(EventLocation): Forecast` | The forecast of the place, its periods in UTC; throws `WeatherUnavailable` when the service fails |

An adapter is named after its service, keeps no state between calls, and sends its requests through the HTTP client it
is given, so that the application's cache applies to it.

## `Contract\AlertSource`

| Method | Contract |
|---|---|
| `attribution(): Attribution` | The attribution of its alerts |
| `supports(EventLocation): bool` | Whether the service issues alerts for the place |
| `alerts(EventLocation): list<WeatherAlert>` | The alerts in effect or announced for the place, ended ones left out; throws `WeatherUnavailable` when the service fails |

`Cap\CapReader` reads the CAP documents of a service that publishes them.

## Arguments

| Point | How |
|---|---|
| The providers and their order | The list given to `EventWeather`, the preferred first |
| The alert sources | The list given to `EventWeather`; the first that supports the place is asked |
| Now | The PSR-20 clock given to `EventWeather` |
| The warnings of failing providers | The PSR-3 logger given to `EventWeather` |
| The cache | The HTTP client given to the adapters, wrapped in `CachingHttpClient` |
| The identification | The `Contact` given to the adapters |
| The language of the providers' words | `en` or `fr`, given to the Environment Canada adapters |
| The endpoints | The `Url` given to each adapter |
| The search distance of a city page site | The `$searchKilometers` of `CitypageWeatherProvider` |
| Catching the library's errors | Every exception implements `Exception\EventWeatherException` |

## Stability

The ports, the value objects and the adapters are the public API. The `Internal\` namespace — `Data`, `JsonClient`,
`Polygon` — is not: it changes without notice.
