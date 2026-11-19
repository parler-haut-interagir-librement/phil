# Providers

| Adapter | Serves | Horizon | Asks |
|---|---|---|---|
| `EnvironmentCanada\CitypageWeatherProvider` | Places whose country is `CA` | 24 hours | `https://api.weather.gc.ca/collections/citypageweather-realtime/items` |
| `MetNorway\LocationforecastProvider` | Every place | 216 hours | `https://api.met.no/weatherapi/locationforecast/2.0/complete` |
| `EnvironmentCanada\WeatherAlertsSource` | Places whose country is `CA` | — | `https://api.weather.gc.ca/collections/weather-alerts/items` |

Each adapter takes the HTTP client, the `Contact`, and an endpoint to replace the default, for a proxy or a test
server. Every request carries the `User-Agent` of the contact, a timeout of 10 seconds, a whole duration of 20 seconds
and at most three redirects; an answer is read up to 8 MiB. A provider that does not answer, answers with a status
other than 200 or 203, or sends what its format does not describe throws `Exception\WeatherUnavailable`.

## Environment Canada: city page forecasts

The provider asks the `citypageweather-realtime` collection of the MSC GeoMet OGC API for the forecast sites in a box
around the event — 50 km by default, from 1 to 200 km by `$searchKilometers` — and keeps the nearest site within that
distance; without one, it throws `WeatherUnavailable`. It reads the site's hourly forecast: for each hour, the
condition in English words read into a `Condition`, the temperature, the likelihood of precipitation, the wind speed,
gust and compass direction; the condition in the language given, `en` or `fr`, is the period's summary.

The daily forecasts of a city page name their periods ("Tonight", "Saturday") in the site's local time, which the
collection does not give; they are not read, and MET Norway, after Environment Canada in the list, forecasts those days.

## Environment Canada: weather alerts

The source asks the `weather-alerts` collection for the alerts around the event, keeps those whose polygon contains it
and that have not ended, and reads each in the language given: its name, short name and text, the colour of its risk —
red is `Extreme`, orange `Severe`, yellow `Moderate`, any other `Unknown` —, when it takes effect and when the event it
announces ends, and the area it names. An alert issued the same for several areas around the event is kept once.

## MET Norway: Locationforecast

The provider asks the `complete` variant for the event's coordinates, cut to four decimals, and reads each time step:
the instant's temperature, wind speed, gust and direction, and the next hour's — or, further ahead, the next six
hours' — symbol, precipitation, chance of precipitation and temperature range. Wind speeds in metres per second become
kilometres per hour. A status 203, MET Norway's notice of a product version about to be retired, is read as a 200.

## CAP alerts

`Cap\CapReader` reads Common Alerting Protocol 1.2 documents, the format of Environment Canada's alerts on its
Datamart and of most national weather services; it is a reader for the application's own alert source — see
[CAP alerts](cap.md).

## Not supported

- MET Norway's MetAlerts, the alerts for Norway.
- Environment Canada's daily city page forecasts, and its Datamart XML files.
- Air quality, marine and aviation forecasts, and current conditions.
- Other services: an application adds one as an adapter of `Contract\ForecastProvider` or `Contract\AlertSource` —
  see [Extension points](extension-points.md).
