<!-- phil-dev source-sha256=08b302ab554e2b77e88d21f2b93e1ae23326e18225b10a00b82844883d2d0d64 -->
# Fournisseurs

| Adaptateur | Sert | Horizon | Interroge |
|---|---|---|---|
| `EnvironmentCanada\CitypageWeatherProvider` | Les lieux dont le pays est `CA` | 24 heures | `https://api.weather.gc.ca/collections/citypageweather-realtime/items` |
| `MetNorway\LocationforecastProvider` | Tous les lieux | 216 heures | `https://api.met.no/weatherapi/locationforecast/2.0/complete` |
| `EnvironmentCanada\WeatherAlertsSource` | Les lieux dont le pays est `CA` | — | `https://api.weather.gc.ca/collections/weather-alerts/items` |

Chaque adaptateur prend le client HTTP, le `Contact` et un point de terminaison qui remplace celui par défaut, pour un
mandataire ou un serveur de test. Chaque requête porte le `User-Agent` du contact, un délai de 10 secondes, une durée
totale de 20 secondes et au plus trois redirections ; une réponse est lue jusqu'à 8 Mio. Un fournisseur qui ne répond
pas, qui répond avec un statut autre que 200 ou 203, ou qui envoie ce que son format ne décrit pas lève
`Exception\WeatherUnavailable`.

## Environnement Canada : prévisions des pages de villes

Le fournisseur interroge la collection `citypageweather-realtime` de l'API OGC MSC GeoMet pour les sites de prévisions
dans un carré autour de l'événement — 50 km par défaut, de 1 à 200 km avec `$searchKilometers` — et garde le site le
plus proche dans cette distance ; sans site, il lève `WeatherUnavailable`. Il lit les prévisions horaires du site :
pour chaque heure, la condition en mots anglais lue dans une `Condition`, la température, la probabilité de
précipitations, la vitesse du vent, les rafales et la direction cardinale ; la condition dans la langue donnée, `en`
ou `fr`, est le résumé de la période.

Les prévisions quotidiennes d'une page de ville nomment leurs périodes (« Ce soir », « Samedi ») à l'heure locale du
site, que la collection ne donne pas ; elles ne sont pas lues, et MET Norway, placé après Environnement Canada dans la
liste, prévoit ces jours.

## Environnement Canada : alertes météo

La source interroge la collection `weather-alerts` pour les alertes autour de l'événement, garde celles dont le
polygone le contient et qui ne sont pas terminées, et lit chacune dans la langue donnée : son nom, son nom court et son
texte, la couleur de son risque — le rouge est `Extreme`, l'orange `Severe`, le jaune `Moderate`, tout autre `Unknown`
—, le moment où elle prend effet et celui où l'événement qu'elle annonce se termine, et la zone qu'elle nomme. Une
alerte émise à l'identique pour plusieurs zones autour de l'événement n'est gardée qu'une fois.

## MET Norway : Locationforecast

Le fournisseur interroge la variante `complete` pour les coordonnées de l'événement, coupées à quatre décimales, et lit
chaque pas de temps : la température, la vitesse du vent, les rafales et la direction de l'instant, et le symbole, les
précipitations, la probabilité de précipitations et la plage de températures de l'heure suivante — ou, plus loin, des
six heures suivantes. Les vitesses du vent en mètres par seconde deviennent des kilomètres par heure. Un statut 203,
l'avis de MET Norway qu'une version du produit sera bientôt retirée, est lu comme un 200.

## Alertes CAP

`Cap\CapReader` lit les documents du Common Alerting Protocol 1.2, le format des alertes d'Environnement Canada sur son
Datamart et de la plupart des services météo nationaux ; c'est un lecteur pour la source d'alertes propre à
l'application — voir [Alertes CAP](cap.md).

## Non pris en charge

- Les MetAlerts de MET Norway, les alertes pour la Norvège.
- Les prévisions quotidiennes des pages de villes d'Environnement Canada, et ses fichiers XML du Datamart.
- Les prévisions de qualité de l'air, marines et aéronautiques, et les conditions actuelles.
- Les autres services : une application en ajoute un comme adaptateur de `Contract\ForecastProvider` ou de
  `Contract\AlertSource` — voir [Points d'extension](extension-points.md).
