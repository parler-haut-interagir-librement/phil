<!-- phil-dev source-sha256=5fdd30f39f7edd9383bbf8867fda0b22902d622bd3491cd4bfd026cca82656a4 -->
# Prévisions

## L'événement

`EventWeather::forecastFor()` prend l'`EventLocation` de l'événement — ses `Phil\Geo\Coordinates` et le pays ISO 3166-1
de son adresse, que l'application connaît et que la bibliothèque ne devine jamais — ainsi que son début et sa fin. Les
instants peuvent être dans n'importe quel fuseau horaire : ils sont comparés et retournés en UTC. Un événement qui se
termine avant de commencer est refusé avec `Exception\InvalidArgument`.

## Le choix d'un fournisseur

Les fournisseurs sont essayés dans l'ordre donné, le préféré en premier. Un fournisseur est essayé quand il prend en
charge le lieu et que l'événement commence avant maintenant plus son horizon ; le premier qui répond avec au moins une
période du moment de l'événement l'emporte. Un fournisseur qui échoue est journalisé comme avertissement et le suivant
est essayé.

Avec Environnement Canada en premier et MET Norway en second, un événement canadien du lendemain obtient les
prévisions horaires d'Environnement Canada ; un événement plus tard dans la semaine obtient celles de MET Norway ; un
événement à l'étranger obtient celles de MET Norway.

## La disponibilité

| `Availability` | Quand |
|---|---|
| `Available` | Un fournisseur a prévu une partie du moment de l'événement |
| `Past` | L'événement est terminé |
| `Unsupported` | Aucun fournisseur ne sert le lieu |
| `TooFar` | L'événement commence au-delà de tous les horizons, ou les fournisseurs ont répondu sans son moment |
| `Unavailable` | Tous les fournisseurs essayés ont échoué |

Seul `Available` porte une prévision : pour les autres, une page n'affiche rien, ou une ligne disant que la prévision
approche de la date. Une prévision indisponible n'est jamais une erreur sur la page de l'événement.

## Ce que contient une prévision d'événement

`EventForecast` porte la `availability`, le `start` et le `end` de l'événement en UTC, la `forecast` complète du
fournisseur, les `periods` qui chevauchent l'événement, les `alerts` en vigueur pendant celui-ci et les `attributions`
de chaque fournisseur dont il contient des données — celle des prévisions, et celle de la source d'alertes quand il a
des alertes. `mostSevereAlert()` et `maxPrecipitationProbability()` le résument pour un badge.

Une `ForecastPeriod` va de `from` à `to` en UTC, avec sa `Condition`, sa `temperature`, sa `minTemperature` et sa
`maxTemperature` en degrés Celsius, sa `precipitationProbability` en pourcentage, sa `precipitation` en millimètres,
son `windSpeed` et son `windGust` en kilomètres par heure, son `windDirection` en degrés d'où souffle le vent, et le
`summary` propre au fournisseur. Ce qu'un fournisseur ne donne pas est nul. `Condition::isWet()` dit si les gens
dehors se font mouiller ou geler.

Une `WeatherAlert` garde les textes du fournisseur tels qu'émis — la licence d'Environnement Canada demande qu'une
alerte soit reproduite sans en altérer le contenu ni l'intention : affichez son `event`, son `headline`, sa
`description` et son `instruction` tels quels, avec sa `severity` et les instants `effective` et `expires`, jamais résumés ni traduits automatiquement — voir
[Afficher les alertes](licences.md#afficher-les-alertes).

## Le rendu

La bibliothèque n'écrit ni balisage ni script. Une page produit le rendu des périodes à sa façon, les alertes en
premier, et les mentions de source à côté des données avec un lien vers chaque licence — voir
[Licences et mentions de source](licences.md).
