<!-- phil-dev source-sha256=a05ef542f4c1ca2c2ac6f796d410bc06aeb4f08d645432007bdfb46aa3dc4c60 -->
# phil/calendar-sync

**La synchronisation de calendriers externes : les plages occupées lues depuis des flux iCalendar, CalDAV, Google
Calendar et Microsoft Graph, et les événements du site écrits dans ces calendriers, chaque service étant un adaptateur
derrière un port.**

Un site qui réserve des gens — un maître de jeu, une salle, un entraîneur — ne doit pas offrir une plage que ses gens
ont déjà donnée ailleurs, et ses réservations doivent apparaître dans les calendriers qu'ils lisent. phil/calendar-sync
fait les deux :

- il lit **quand** quelqu'un est pris dans un de ses calendriers, jamais **ce qui** occupe la plage : des plages
  occupées seulement, un début, une fin et un indicateur provisoire — jamais un titre, un lieu, une note ni un invité ;
- il écrit les événements du site dans ce calendrier, remplacés quand ils sont écrits de nouveau, retirés quand ils sont
  retirés.

Chaque service est un adaptateur, nommé par la connexion qui l'utilise : `ics` lit un flux iCalendar publié, `caldav`
une collection CalDAV, `google` un calendrier Google, `microsoft` un calendrier Outlook par Microsoft Graph. Une
application ajoute les siens en implémentant les deux mêmes ports.

L'application garde les connexions, leurs identifiants de connexion et les plages occupées lues, derrière des ports : le
paquet ne stocke rien et ne garde rien entre deux appels. Les identifiants de connexion sont demandés à chaque appel et
ne peuvent pas être sérialisés. Les messages de Messenger portent l'identifiant d'une connexion et celui d'un événement,
jamais un jeton, une adresse ni une heure lue.

Chaque adresse est lue par `phil/url`. Un flux est lu par `phil/safe-http`, et le client CalDAV est enveloppé dans le
`NoPrivateNetworkHttpClient` de Symfony : ni l'un ni l'autre n'atteint un réseau privé, et aucune redirection ne
transmet d'identifiants de connexion à un autre hôte. Chaque lecture est bornée en temps, en octets, en composants, en
pages et en périodes ; le lecteur iCalendar est soumis au fuzzing.

phil/calendar-sync est une bibliothèque PHP ordinaire, qui s'appuie sur le HttpClient et le Clock de Symfony, et sur
`phil/recurrence` pour les règles des événements qu'elle lit.

## État

| Partie | État |
|---|---|
| `CalendarSync`, les ports, `BusyPeriod`, `BusyPeriods`, `SyncWindow`, `CalendarConnection`, `Credentials`, `OutboundEvent` | Implémenté et testé |
| Le lecteur iCalendar — événements, règles, occurrences ajoutées, retirées et modifiées, plages libre/occupé | Implémenté, testé et soumis au fuzzing |
| Le rédacteur iCalendar | Implémenté et testé |
| Les adaptateurs `ics`, `caldav`, `google` et `microsoft` | Implémenté et testé contre des doublures HTTP |
| Les messages `RefreshBusyTimes` et `SyncEvent` et leurs *handlers* | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage des services. |
| [Synchronisation](doc/fr/synchronizing.md) | Les connexions, les identifiants de connexion, les plages occupées, les événements écrits, les messages, les échecs. |
| [Adaptateurs](doc/fr/adapters.md) | Ce que lit et écrit chaque adaptateur, son calendrier, ses identifiants de connexion et ses bornes. |
| [Le lecteur iCalendar](doc/fr/icalendar.md) | Ce qui est lu d'un calendrier, les fuseaux horaires, les bornes et ce qui est refusé. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, ses propres adaptateurs, et ce qui reste fermé. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/calendar-sync
```

```php
use Phil\CalendarSync\Adapter\CalDav;
use Phil\CalendarSync\Adapter\GoogleCalendar;
use Phil\CalendarSync\Adapter\IcsFeed;
use Phil\CalendarSync\Adapter\MicrosoftGraph;
use Phil\CalendarSync\CalendarSync;
use Phil\CalendarSync\SyncWindow;

$writers = [new CalDav(), new GoogleCalendar(), new MicrosoftGraph()];
$sync = new CalendarSync($connections, $credentials, $busyStore, sources: [new IcsFeed(), ...$writers], destinations: $writers);

// The next 60 days of a member's calendar, joined into the fewest periods and given to the store.
$sync->refreshBusy($connectionId, SyncWindow::days(new DateTimeImmutable('today'), 60));

// A booking written into it, then withdrawn.
$sync->publish($connectionId, $booking);
$sync->withdraw($connectionId, $booking->uid);
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-dom`, `ext-intl`, `ext-libxml` et `ext-mbstring` ; le HttpClient, la HTTP
foundation et le Clock de Symfony 8.2 ; `phil/recurrence`, `phil/safe-http` et `phil/url`. Symfony Messenger est
facultatif.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
