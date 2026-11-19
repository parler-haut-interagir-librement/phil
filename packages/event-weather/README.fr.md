<!-- phil-dev source-sha256=1a08f1e3f3d0028733ce946ff5a336d52276f18b8dc5ebef6343eb0da18d9e87 -->
# phil/event-weather

**Affichez les prévisions et les alertes météo d'un événement extérieur sur sa page, à partir du service météo de son lieu.**

phil/event-weather donne les prévisions du lieu et du moment d'un événement : les périodes de l'événement, en UTC, avec
leur condition, leurs températures, la probabilité et la quantité de précipitations et le vent, les veilles et les
avertissements en vigueur pendant l'événement, et la mention de source qu'exige la licence de chaque fournisseur.
Environnement et Changement climatique Canada sert les lieux canadiens, avec ses alertes ; MET Norway sert tous les
lieux et prend le relais au-delà de l'horizon d'Environnement Canada. Une prévision n'est affichée que dans l'horizon
d'un fournisseur : un événement plus lointain est trop loin pour être prévu.

Le paquet s'adresse à toute application PHP qui liste des événements extérieurs — un festival, un marché, une journée de
jeu dans un parc — et veut que leur page dise s'il faut apporter un parapluie.

Une alerte n'est jamais résumée ni traduite automatiquement : une page montre son texte tel qu'il a été émis, dans la
langue que donne la source, comme l'exige la licence d'Environnement et Changement climatique Canada — voir
[Afficher les alertes](doc/fr/licences.md#afficher-les-alertes).

C'est une bibliothèque PHP simple, bâtie sur les contrats du client HTTP de Symfony, une horloge PSR-20 et un
journaliseur PSR-3, avec `phil/geo` et `phil/url`. L'application lui donne un client HTTP enveloppé dans le
`CachingHttpClient` de Symfony, qui respecte l'en-tête `Expires` des fournisseurs et revalide avec
`If-Modified-Since`, et sa propre identification pour le `User-Agent`. Le paquet lit aussi les alertes du Common
Alerting Protocol 1.2.

La conception : `EventWeather` essaie les `Contract\ForecastProvider` dans l'ordre, interroge la première
`Contract\AlertSource` du lieu et retourne un `EventForecast` — son `Availability`, ses `ForecastPeriod`, ses
`WeatherAlert` et ses `Attribution` ; `EnvironmentCanada\CitypageWeatherProvider`,
`EnvironmentCanada\WeatherAlertsSource` et `MetNorway\LocationforecastProvider` sont les adaptateurs ;
`Cap\CapReader` lit les documents CAP.

## État

| Partie | État |
|---|---|
| Les prévisions d'un événement : fournisseur selon le lieu et l'horizon, périodes de l'événement, alertes pendant celui-ci, mentions de source | Implémenté et testé |
| Environnement Canada : prévisions horaires des pages de villes du site le plus proche, depuis MSC GeoMet | Implémenté et testé |
| Environnement Canada : alertes météo publiques dont la zone contient l'événement, depuis MSC GeoMet | Implémenté et testé |
| MET Norway : Locationforecast 2.0, horaire puis aux six heures, neuf jours à l'avance | Implémenté et testé |
| Alertes du Common Alerting Protocol 1.2, par langue | Implémenté et testé |
| Cache qui respecte `Expires` et `If-Modified-Since`, par le `CachingHttpClient` de Symfony | Implémenté et testé |
| Prévisions quotidiennes d'Environnement Canada, au-delà de son horizon horaire | Pas commencé : MET Norway couvre ces jours |
| Alertes de MET Norway (MetAlerts) pour la Norvège | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le cache, l'identification, les services. |
| [Prévisions](doc/fr/forecasts.md) | Comment un fournisseur est choisi, la disponibilité d'une prévision, les périodes et les alertes. |
| [Fournisseurs](doc/fr/providers.md) | Environnement Canada et MET Norway : ce que chacun sert, comment on l'interroge, ce qui est lu. |
| [Licences et mentions de source](doc/fr/licences.md) | Les licences et les conditions de chaque fournisseur, la mention de source à afficher et la façon dont une page affiche une alerte. |
| [Alertes CAP](doc/fr/cap.md) | La lecture des documents du Common Alerting Protocol 1.2. |
| [Sécurité](doc/fr/security.md) | Les requêtes envoyées, les réponses lues et ce qui reste à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests et la cible de fuzzing de la bibliothèque, et le test du code qui affiche des prévisions. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/event-weather symfony/http-client symfony/cache symfony/clock
```

```php
use Phil\EventWeather\Contact;
use Phil\EventWeather\EnvironmentCanada\CitypageWeatherProvider;
use Phil\EventWeather\EnvironmentCanada\WeatherAlertsSource;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\EventWeather;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Phil\Geo\Coordinates;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new CachingHttpClient(HttpClient::create(), new FilesystemTagAwareAdapter('weather'), sharedCache: false);
$contact = new Contact('EventsApp/1.4', 'https://events.example/contact');

$weather = new EventWeather(
    [new CitypageWeatherProvider($client, $contact, 'fr'), new LocationforecastProvider($client, $contact)],
    new NativeClock(),
    [new WeatherAlertsSource($client, $contact, 'fr')],
);

$forecast = $weather->forecastFor(
    new EventLocation(Coordinates::of(45.5017, -73.5673), 'CA'),
    new DateTimeImmutable('2026-11-14 13:00', new DateTimeZone('America/Toronto')),
    new DateTimeImmutable('2026-11-14 17:00', new DateTimeZone('America/Toronto')),
);

if ($forecast->isAvailable()) {
    // $forecast->periods, $forecast->alerts, and $forecast->attributions beside them.
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-dom` et `ext-mbstring`, et une implémentation des contrats du client
HTTP de Symfony.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
