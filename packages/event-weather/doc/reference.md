# Reference

| Type | Role |
|---|---|
| `EventWeather` | The forecast of an event: the provider by place and horizon, the periods and alerts of its time |
| `EventForecast` | What a page shows: availability, periods, alerts, attributions |
| `Availability` | Whether there is a forecast, and why not |
| `EventLocation` | The coordinates and country of an event |
| `Contact` | The application's identification, the `User-Agent` of the requests |
| `Forecast` | One provider's forecast: attribution, time of issue, periods |
| `ForecastPeriod` | The weather of a stretch of time, in UTC and metric units |
| `Condition` | The sky of a period |
| `WeatherAlert` | A watch, warning or advisory, as issued |
| `AlertSeverity` | The CAP severity scale |
| `Attribution` | The credit line, licence and source of a provider |
| `Contract\ForecastProvider` | The port of a forecasting service |
| `Contract\AlertSource` | The port of an alerting service |
| `EnvironmentCanada\CitypageWeatherProvider` | Environment Canada's hourly city page forecasts |
| `EnvironmentCanada\WeatherAlertsSource` | Environment Canada's public weather alerts |
| `MetNorway\LocationforecastProvider` | MET Norway's Locationforecast 2.0 |
| `Cap\CapReader` | Reads Common Alerting Protocol 1.2 documents |
| `Exception\EventWeatherException` | The interface of every exception of the library |
| `Exception\WeatherUnavailable` | A provider that failed |
| `Exception\InvalidArgument` | A value of the application refused |
| `Exception\InvalidCapDocument` | A CAP document refused |
| `Internal\*` | The HTTP reading, the JSON helpers and the polygons: not part of the public API |
