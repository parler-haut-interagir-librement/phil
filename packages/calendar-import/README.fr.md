<!-- phil-dev source-sha256=52c3ab455cd799f543cc9d34aad0c7f4f0d605c2831cc508936ab145354b7004 -->
# phil/calendar-import

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/calendar-import/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/calendar-import)

**Gardez un calendrier à jour avec des flux iCalendar externes : lus par URL à intervalle régulier,
changements détectés, dernier bon état conservé quand un flux échoue, modifications des éditeurs préservées.**

phil/calendar-import est une bibliothèque PHP ordinaire. Elle lit des flux iCalendar (RFC 5545) de façon stricte et
bornée, par `phil/safe-http`, et dit ce qui a changé depuis la dernière lecture : événements ajoutés, modifiés et
supprimés. Un flux qui échoue garde son dernier bon état ; les modifications d'un éditeur sur un événement importé
survivent à chaque lecture. Ce qui en sort, ce sont des brouillons neutres, marqués de leur source et de leur UID,
qu'une application transforme en événements de son propre calendrier.

## État

| Partie | État |
|---|---|
| `ICalendarReader`, `ReaderLimits`, `Rejection` | Implémenté et testé |
| `EventSource`, `IcsUrlSource`, `CalendarSource`, `FetchValidators` | Implémenté et testé |
| `Snapshot`, `SnapshotDiff`, `SourceState`, `RefreshPolicy` | Implémenté et testé |
| `EditorOverride`, `CalendarImporter`, `ImportedDraft` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Lire de l'iCalendar](doc/fr/reading.md) | Ce que le lecteur accepte et refuse, les fuseaux horaires, les bornes. |
| [Sources](doc/fr/sources.md) | Le port `EventSource`, le flux lu par URL, les lectures conditionnelles, la sécurité. |
| [Rafraîchir une source](doc/fr/refreshing.md) | Instantanés, différences, échecs, politique de rafraîchissement, surcharges des éditeurs, brouillons. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, et l'adaptateur qui écrit les brouillons dans un calendrier. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/calendar-import
```

```php
use Phil\CalendarImport\CalendarImporter;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\IcsUrlSource;
use Symfony\Component\Clock\NativeClock;

$importer = new CalendarImporter(IcsUrlSource::create(), $stateStore, $overrideStore, new NativeClock());
$outcome = $importer->refresh(CalendarSource::of('library-events', 'webcal://calendar.example.org/public.ics', 'America/Toronto'));

foreach ($outcome->changes as $change) {
    // $change->kind: Added, Changed or Removed; $change->draft: an ImportedDraft
}
```

`$stateStore` et `$overrideStore` implémentent les deux ports de stockage de la bibliothèque ; la bibliothèque
elle-même ne garde rien entre deux requêtes.

## Prérequis

PHP 8.5 ou une version plus récente, avec `intl` et `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
