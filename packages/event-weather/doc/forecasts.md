# Forecasts

## The event

`EventWeather::forecastFor()` takes the event's `EventLocation` — its `Phil\Geo\Coordinates` and the ISO 3166-1
country of its address, which the application knows and the library never guesses — and its start and end. The
instants may be in any time zone: they are compared, and returned, in UTC. An event that ends before it starts is
refused with `Exception\InvalidArgument`.

## Choosing a provider

The providers are tried in the order given, the preferred first. A provider is tried when it supports the place and
the event starts before now plus its horizon; the first that answers with some period of the event's time wins. A
provider that fails is logged as a warning and the next one tried.

With Environment Canada first and MET Norway second, a Canadian event of the next day gets Environment Canada's hourly
forecast; one later in the week gets MET Norway's; an event abroad gets MET Norway's.

## Availability

| `Availability` | When |
|---|---|
| `Available` | A provider forecast some of the event's time |
| `Past` | The event has ended |
| `Unsupported` | No provider serves the place |
| `TooFar` | The event starts beyond every horizon, or the providers answered without its time |
| `Unavailable` | Every provider tried failed |

Only `Available` carries a forecast: a page shows nothing, or a line saying the forecast comes closer to the date, for
the others. An unavailable forecast is never an error on the event's page.

## What an event forecast holds

`EventForecast` carries the `availability`, the event's `start` and `end` in UTC, the provider's whole `forecast`, the
`periods` that overlap the event, the `alerts` in effect during it, and the `attributions` of every provider whose data
it holds — the forecast's, and the alert source's when it has alerts. `mostSevereAlert()` and
`maxPrecipitationProbability()` sum it up for a badge.

A `ForecastPeriod` runs from `from` to `to` in UTC, with its `Condition`, `temperature`, `minTemperature` and
`maxTemperature` in degrees Celsius, `precipitationProbability` in percent, `precipitation` in millimetres, `windSpeed`
and `windGust` in kilometres per hour, `windDirection` in degrees it blows from, and the provider's own `summary`. What
a provider does not give is null. `Condition::isWet()` says whether people outdoors get wet or cold.

A `WeatherAlert` keeps the provider's texts as issued — Environment Canada's licence asks that an alert be reproduced
without altering its content or intent: show its `event`, `headline`, `description` and `instruction` as they are, with
their `severity` and the `effective` and `expires` instants, never summarized nor machine-translated — see
[Showing alerts](licences.md#showing-alerts).

## Rendering

The library writes no markup and no script. A page renders the periods its own way, the alerts first, and the
attributions beside the data with a link to each licence — see [Licences and attribution](licences.md).
