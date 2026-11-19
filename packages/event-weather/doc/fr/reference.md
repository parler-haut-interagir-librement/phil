<!-- phil-dev source-sha256=1111579c5d07021ca7e80911c6d5d4f5bc439879e201c9b338a1aafabf17cb8b -->
# Référence

| Type | Rôle |
|---|---|
| `EventWeather` | Les prévisions d'un événement : le fournisseur selon le lieu et l'horizon, les périodes et les alertes de son moment |
| `EventForecast` | Ce qu'affiche une page : disponibilité, périodes, alertes, mentions de source |
| `Availability` | S'il y a une prévision, et sinon pourquoi |
| `EventLocation` | Les coordonnées et le pays d'un événement |
| `Contact` | L'identification de l'application, le `User-Agent` des requêtes |
| `Forecast` | La prévision d'un fournisseur : mention de source, heure d'émission, périodes |
| `ForecastPeriod` | Le temps d'un intervalle, en UTC et en unités métriques |
| `Condition` | Le ciel d'une période |
| `WeatherAlert` | Une veille, un avertissement ou un avis, tel qu'émis |
| `AlertSeverity` | L'échelle de gravité de CAP |
| `Attribution` | La mention de source, la licence et la source d'un fournisseur |
| `Contract\ForecastProvider` | Le port d'un service de prévisions |
| `Contract\AlertSource` | Le port d'un service d'alertes |
| `EnvironmentCanada\CitypageWeatherProvider` | Les prévisions horaires des pages de villes d'Environnement Canada |
| `EnvironmentCanada\WeatherAlertsSource` | Les alertes météo publiques d'Environnement Canada |
| `MetNorway\LocationforecastProvider` | Locationforecast 2.0 de MET Norway |
| `Cap\CapReader` | Lit les documents du Common Alerting Protocol 1.2 |
| `Exception\EventWeatherException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\WeatherUnavailable` | Un fournisseur qui a échoué |
| `Exception\InvalidArgument` | Une valeur de l'application refusée |
| `Exception\InvalidCapDocument` | Un document CAP refusé |
| `Internal\*` | La lecture HTTP, les utilitaires JSON et les polygones : hors de l'API publique |
