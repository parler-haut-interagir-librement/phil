<!-- phil-dev source-sha256=3be9e2449b2d6a057e1d7a1375c89bb1f8ccf46ead76aaff34de35db0241e79f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/event-weather` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/http-client-contracts` 3.8).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `EventWeather` : les prévisions du lieu et du moment d'un événement, les fournisseurs essayés dans l'ordre donné par
  lieu et par horizon, un fournisseur défaillant journalisé et le suivant essayé, les alertes de la première source
  d'alertes du lieu, chaque instant comparé en UTC sur une horloge PSR-20.
- `EventForecast` avec sa `Availability` — disponible, passée, non prise en charge, trop lointaine, indisponible —, les
  périodes et les alertes de l'événement, les attributions, `mostSevereAlert()` et `maxPrecipitationProbability()`.
- `EventLocation`, `Contact`, `Forecast`, `ForecastPeriod` en UTC et en unités métriques, `Condition`, `WeatherAlert`
  avec ses textes gardés tels que publiés, `AlertSeverity` et `Attribution`.
- Les ports `Contract\ForecastProvider` et `Contract\AlertSource`.
- `EnvironmentCanada\CitypageWeatherProvider` : les prévisions horaires du site de page de ville le plus proche, à
  partir de l'API OGC MSC GeoMet, pour les lieux canadiens.
- `EnvironmentCanada\WeatherAlertsSource` : les alertes météo publiques d'Environnement Canada dont la zone contient
  l'événement, celles terminées laissées de côté, la même alerte pour plusieurs zones gardée une fois.
- `MetNorway\LocationforecastProvider` : Locationforecast 2.0 pour chaque lieu, les coordonnées coupées à quatre
  décimales.
- Chaque requête identifiée par le `Contact` de l'application, bornée en temps, en redirections et en octets ; la mise
  en cache par le `CachingHttpClient` de Symfony, qui respecte `Expires` et `If-Modified-Since`.
- `Cap\CapReader` : les alertes Common Alerting Protocol 1.2 par langue, les alertes réelles seulement, qui refuse les
  types de documents, l'entrée qui n'est pas en UTF-8 et les documents au-delà de ses limites.
- Les attributions et les licences d'Environnement et Changement climatique Canada et de MET Norway, vérifiées d'après
  leurs conditions, et la règle pour les sites qui montrent une alerte : jamais résumée ni traduite par une machine, son
  texte montré tel que publié dans la langue que donne la source.
- Une cible de fuzzing pour le lecteur CAP.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
