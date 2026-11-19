<!-- phil-dev source-sha256=57d6a7da9cb0dd74fb9116965f406f054b05efed885e79aed4386a557eb1ee04 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl` | n'importe laquelle | Le moment où une occurrence a lieu, écrit par ICU |
| `ext-mbstring` | n'importe laquelle | Les longueurs et le repliement des lignes en caractères |
| `ext-sodium` | n'importe laquelle | Le secret du flux d'un membre, écrit en hexadécimal |
| `phil/recurrence`, `phil/recurrence-bundle` | ^0.1@dev | Les règles, leur type de colonne `phil_rrule` et leur champ de formulaire |
| `phil/doctrine-behaviours`, `phil/locale-dimensions` | ^0.1@dev | Les traductions des événements et des lieux, et leurs locales |
| `doctrine/dbal` | ^4.5 | Les décomptes et les listes de participants |
| `symfony/workflow`, `symfony/event-dispatcher-contracts` | ^8.2, ^3.6 | Le flux de travail des propositions, et ses événements |
| `symfony/translation-contracts` | ^3.6 | La locale de la requête, lue à partir du traducteur |
| `phil/url` | ^0.1@dev | La page et l'image d'un événement, lues comme un navigateur les lit |
| `doctrine/orm` | ^3.7 | Les entités et le dépôt |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | Le formulaire d'événement et son brouillon |
| `symfony/http-foundation` | ^8.2 | Le flux iCalendar |
| `symfony/uid` | ^8.2 | Les identifiants d'événement, UUID v7 |
| `psr/log` | ^3.0 | L'avertissement d'une requête arrêtée à son budget ; le service `logger` du conteneur quand il y en a un |
| `psr/clock` | ^1.0 | L'heure du fichier iCalendar et la date du jour dans les vues ; le service `clock` du conteneur, que FrameworkBundle enregistre dès que `symfony/clock` est installé |
| `twig/twig` | facultatif | `calendar_occurrences`, `occurrence_when` et les filtres des vues |
| `symfony/ux-live-component`, `symfony/ux-twig-component` | facultatif | Le calendrier dans une page, `<twig:PhilCalendar:Calendar>` |
| `symfony/stimulus-bundle` | facultatif | Le clavier dans la grille du mois ; et le contrôleur propre au composant |
| `symfony/asset-mapper` | facultatif | Avec AssetMapperBundle enregistré, le contrôleur servi sous `@phil/calendar-bundle/` et la feuille de style sous `phil-calendar/calendar.css` |
| `phil/privacy-bundle` | facultatif | Les choix, le flux, les participations et les propositions d'un membre dans l'exportation et l'effacement de ses données |
| `phil/ux-kit` | facultatif | Les recettes Popover, Badge, Card, Button, ButtonGroup et Empty que dessinent les vues, installées par le Symfony UX Toolkit, et les jetons de la feuille de style |
| `phil/paged-media` | facultatif | Le calendrier imprimé, un mois par page |
| `phil/almanac`, `phil/almanac-bundle` | facultatif | Les dates de l'almanach parmi les événements, et en événements |
| `phil/taxonomy-bundle` | facultatif | Les étiquettes des événements partagées avec le site, renommées et fusionnées à un seul endroit |
| `symfony/console` | facultatif | `phil:calendar:import-almanac`, `phil:calendar:refresh-tags` |

```sh
composer require phil/calendar-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_calendar.yaml` avec le fuseau horaire et
le premier jour de la semaine d'un nouveau calendrier, et importe les routes sous `/calendar`. Sans elle, enregistrez
les deux bundles dans `config/bundles.php` :

```php
// config/bundles.php
return [
    // ...
    Phil\RecurrenceBundle\PhilRecurrenceBundle::class => ['all' => true],
    Phil\CalendarBundle\PhilCalendarBundle::class => ['all' => true],
];
```

Sans `PhilRecurrenceBundle`, le conteneur ne se construit pas : le bundle le dit.

```yaml
# config/packages/phil_calendar.yaml
phil_calendar:
    default_timezone: America/Toronto   # a new event's time zone, an IANA identifier; UTC when left out
    name: Agenda                        # the calendar's name in its iCalendar file; none when left out
    first_weekday: 1                    # the day weeks start on in the views, 1 Monday to 7 Sunday
    kit_subdirectory: ~                 # where the recipes of the PHIL UX kit are; the Toolkit's subdirectory when null
    budget:
        occurrences: 10000              # the occurrences one request unrolls at most
        expansions: 500                 # the repeating events one request reads at most
    privacy_member_kind: member         # the kind of member a data subject of phil/privacy-bundle is
    translations:
        locales: { fr_CA: [fr], fr: [], en: [] }   # the locales events are shown in; none by default
```

## Les tables

Avec DoctrineBundle, le bundle mappe lui-même ses entités ; les migrations de l'application créent huit tables :

| Table | Colonnes |
|---|---|
| `phil_calendar_event` | `id` (UUID, 36), `title` (200), `description` (texte), `location` (255), `url` (2048), `venue` (mis à null à la suppression), `image` (2048), `starts_at`, `ends_at` (`datetime_utc_immutable`), `all_day`, `timezone` (64), `rule` (`phil_rrule`), `exceptions` (JSON), `added_dates` (JSON, null pour aucune), `tags` (JSON), `cancelled`, `date_links` (JSON), `capacity`, `proposal_state` (16, `published` par défaut), `proposed_by` (129), `series_ends_at` (`datetime_utc_immutable`, null pour une règle sans fin), `version` ; un index sur `starts_at, series_ends_at` |
| `phil_calendar_event_host` | `id`, `event` (en cascade à la suppression), `kind` (64), `reference` (64) ; un index sur `kind, reference` |
| `phil_calendar_venue` | `id` (UUID, 36), `name` (200), `room` (100), `address` (500), `latitude`, `longitude`, `listing` (129, `kind:reference`) ; un index sur `listing` |
| `phil_calendar_event_translation`, `phil_calendar_venue_translation` | Voir [Traductions](translations.md) |
| `phil_calendar_attendance` | `id` (UUID, 36), `event` (en cascade à la suppression), `occurrence` (19, son début en UTC, vide pour toutes les occurrences), `member` (129), `created_at` (`datetime_utc_immutable`) ; unique sur `event, occurrence, member`, un index sur `member` |
| `phil_calendar_choice` | `id` (UUID, 36), `member` (129), `source` (16), `value` (129), `hidden`, `chosen_at` (`datetime_utc_immutable`) ; unique sur `member, source, value` |
| `phil_calendar_feed_token` | `id` (UUID, 36), `member` (129, unique), `digest` (64, le SHA-256 du secret, unique), `issued_at` (`datetime_utc_immutable`) |

```sh
bin/console doctrine:migrations:diff
```

## Les routes

```yaml
# config/routes/phil_calendar.yaml
phil_calendar:
    resource: '@PhilCalendarBundle/config/routes.php'
    prefix: /calendar
```

Le flux du calendrier est `phil_calendar_ics`, `/calendar.ics` sous le préfixe ; celui d'un membre est
`phil_calendar_personal_ics`, `/personal/{token}.ics` ([Le flux](feeds.md)).

## Le calendrier dans une page

Avec Symfony UX (`symfony/ux-live-component`, `symfony/ux-twig-component` et `symfony/stimulus-bundle` enregistrés, et
la route de Live Component importée), le composant est prêt :

```twig
<link rel="stylesheet" href="{{ asset('phil-calendar/calendar.css') }}">

<twig:PhilCalendar:Calendar />
```

### Les recettes du kit

Les gabarits dessinent leurs bulles, leurs badges, leurs cartes, leurs boutons et leurs états vides avec les recettes du
kit PHIL UX, installées dans l'application par le Symfony UX Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install popover badge card button button-group empty --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les recettes ont les noms, les propriétés et les blocs de celles du kit Shadcn : un site sur Tailwind les installe donc
depuis le kit Shadcn. Les gabarits les appellent par `phil_calendar_kit('Popover')`, sous le sous-répertoire des
composants de la configuration `ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, comme l'est souvent
la production, répétez-le dans `kit_subdirectory`.

La recette Popover apporte son contrôleur Stimulus, `popover`, qui place le panneau à côté de son bouton ; sans lui,
l'API popover du navigateur ouvre et ferme quand même le panneau.

`assets/package.json` déclare le contrôleur Stimulus `calendar`, `phil--calendar-bundle--calendar`, chargé
paresseusement : son code est chargé la première fois que la grille d'un mois apparaît sur une page. Avec
AssetMapperBundle enregistré, le bundle écrit dans l'extension `asset_mapper` les chemins `assets/src` sous
`@phil/calendar-bundle/` et `assets/styles` sous `phil-calendar/` ; sans lui, il n'écrit rien. Avec un autre pipeline,
importez `vendor/phil/calendar-bundle/assets/styles/calendar.css`. Les vues sont décrites dans [views.md](views.md).
