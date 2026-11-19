# Changelog

All notable changes to `phil/event-weather` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `EventWeather`: the forecast of an event's place and time, the providers tried in the order given by place and
  horizon, a failing provider logged and the next tried, the alerts of the first alert source of the place, every
  instant compared in UTC on a PSR-20 clock.
- `EventForecast` with its `Availability` — available, past, unsupported, too far, unavailable —, the periods and
  alerts of the event, the attributions, `mostSevereAlert()` and `maxPrecipitationProbability()`.
- `EventLocation`, `Contact`, `Forecast`, `ForecastPeriod` in UTC and metric units, `Condition`, `WeatherAlert` with its
  texts kept as issued, `AlertSeverity` and `Attribution`.
- The ports `Contract\ForecastProvider` and `Contract\AlertSource`.
- `EnvironmentCanada\CitypageWeatherProvider`: the hourly forecast of the nearest city page site, from the MSC GeoMet
  OGC API, for Canadian places.
- `EnvironmentCanada\WeatherAlertsSource`: Environment Canada's public weather alerts whose area contains the event,
  ended ones left out, the same alert for several areas kept once.
- `MetNorway\LocationforecastProvider`: Locationforecast 2.0 for every place, coordinates cut to four decimals.
- Each request identified by the application's `Contact`, bounded in time, redirects and bytes; caching through
  Symfony's `CachingHttpClient`, which honours `Expires` and `If-Modified-Since`.
- `Cap\CapReader`: Common Alerting Protocol 1.2 alerts by language, actual alerts only, refusing document types,
  non-UTF-8 input and documents past its limits.
- The attributions and licences of Environment and Climate Change Canada and MET Norway, checked against their terms,
  and the rule for sites that show an alert: never summarized nor machine-translated, its text shown as issued in the
  language the source gives.
- A fuzz target for the CAP reader.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
