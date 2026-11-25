# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl` | any | When an occurrence takes place, written by ICU |
| `ext-mbstring` | any | Lengths and line folding in characters |
| `ext-sodium` | any | The secret of a member's feed, written in hexadecimal |
| `phil/recurrence`, `phil/recurrence-bundle` | ^0.1@dev | The rules, their column type `phil_rrule` and their form field |
| `phil/doctrine-behaviours`, `phil/locale-dimensions` | ^0.1@dev | The translations of events and venues, and their locales |
| `doctrine/dbal` | ^4.5 | The counts and lists of attendees |
| `symfony/workflow`, `symfony/event-dispatcher-contracts` | ^8.2, ^3.6 | The workflow of proposals, and its events |
| `symfony/translation-contracts` | ^3.6 | The request's locale, read from the translator |
| `phil/url` | ^0.1@dev | The page and the image of an event, read as a browser reads them |
| `doctrine/orm` | ^3.7 | The entities and the repository |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | The event form and its draft |
| `symfony/http-foundation` | ^8.2 | The iCalendar feed |
| `symfony/uid` | ^8.2 | Event identifiers, UUID v7 |
| `psr/log` | ^3.0 | The warning of a request stopped at its budget; the container's `logger` service when there is one |
| `psr/clock` | ^1.0 | The time of the iCalendar file and today's date in the views; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `twig/twig` | optional | `calendar_occurrences`, `occurrence_when` and the views' filters |
| `symfony/ux-live-component`, `symfony/ux-twig-component` | optional, 3.8.1 and 3.8 or later | The calendar on a page, `<twig:PhilCalendar:Calendar>` |
| `symfony/stimulus-bundle` | optional | The keyboard in the month's grid; and the component's own controller |
| `symfony/asset-mapper` | optional | With AssetMapperBundle registered, the controller served under `@phil/calendar-bundle/` and the stylesheet as `phil-calendar/calendar.css` |
| `phil/privacy-bundle` | optional | A member's choices, feed, attendances and proposals in the export and the erasure of their data |
| `phil/ux-kit` | optional | The Popover, Badge, Card, Button, ButtonGroup and Empty recipes the views draw, installed by the Symfony UX Toolkit, and the tokens of the stylesheet |
| `phil/paged-media` | optional | The calendar printed, a month per page |
| `phil/almanac`, `phil/almanac-bundle` | optional | The almanac's dates among the events, and made events |
| `phil/taxonomy-bundle` | optional | The events' tags shared with the site, renamed and merged in one place |
| `symfony/console` | optional | `phil:calendar:import-almanac`, `phil:calendar:refresh-tags` |

```sh
composer require phil/calendar-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_calendar.yaml` with the time zone and
first weekday of a new calendar, and imports the routes under `/calendar`. Without it, register both bundles in
`config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    Phil\RecurrenceBundle\PhilRecurrenceBundle::class => ['all' => true],
    Phil\CalendarBundle\PhilCalendarBundle::class => ['all' => true],
];
```

Without `PhilRecurrenceBundle` the container does not build: the bundle says so.

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

## The tables

With DoctrineBundle, the bundle maps its entities itself; the application's migrations create eight tables:

| Table | Columns |
|---|---|
| `phil_calendar_event` | `id` (UUID, 36), `title` (200), `description` (text), `location` (255), `url` (2048), `venue` (sets null on delete), `image` (2048), `starts_at`, `ends_at` (`datetime_utc_immutable`), `all_day`, `timezone` (64), `rule` (`phil_rrule`), `exceptions` (JSON), `added_dates` (JSON, null for none), `tags` (JSON), `cancelled`, `date_links` (JSON), `capacity`, `proposal_state` (16, `published` by default), `proposed_by` (129), `series_ends_at` (`datetime_utc_immutable`, null for an endless rule), `version`; an index on `starts_at, series_ends_at` |
| `phil_calendar_event_host` | `id`, `event` (cascades on delete), `kind` (64), `reference` (64); an index on `kind, reference` |
| `phil_calendar_venue` | `id` (UUID, 36), `name` (200), `room` (100), `address` (500), `latitude`, `longitude`, `listing` (129, `kind:reference`); an index on `listing` |
| `phil_calendar_event_translation`, `phil_calendar_venue_translation` | See [Translations](translations.md) |
| `phil_calendar_attendance` | `id` (UUID, 36), `event` (cascades on delete), `occurrence` (19, its start in UTC, empty for every occurrence), `member` (129), `created_at` (`datetime_utc_immutable`); unique on `event, occurrence, member`, an index on `member` |
| `phil_calendar_choice` | `id` (UUID, 36), `member` (129), `source` (16), `value` (129), `hidden`, `chosen_at` (`datetime_utc_immutable`); unique on `member, source, value` |
| `phil_calendar_feed_token` | `id` (UUID, 36), `member` (129, unique), `digest` (64, the SHA-256 of the secret, unique), `issued_at` (`datetime_utc_immutable`) |

```sh
bin/console doctrine:migrations:diff
```

## The routes

```yaml
# config/routes/phil_calendar.yaml
phil_calendar:
    resource: '@PhilCalendarBundle/config/routes.php'
    prefix: /calendar
```

The calendar's feed is `phil_calendar_ics`, `/calendar.ics` under the prefix; a member's own is
`phil_calendar_personal_ics`, `/personal/{token}.ics` ([The feed](feeds.md)).

## The calendar on a page

With Symfony UX — `symfony/ux-live-component`, `symfony/ux-twig-component` and `symfony/stimulus-bundle` registered,
and the Live Component route imported —, the component is ready:

```twig
<link rel="stylesheet" href="{{ asset('phil-calendar/calendar.css') }}">

<twig:PhilCalendar:Calendar />
```

### The recipes of the kit

The templates draw their popovers, badges, cards, buttons and empty states with the recipes of the PHIL UX kit,
installed in the application by the Symfony UX Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install popover badge card button button-group empty --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipes have the names, props and blocks of the Shadcn kit's, so a site on Tailwind installs them from the Shadcn
kit instead. The templates call them through `phil_calendar_kit('Popover')`, under the components subdirectory of the
`ux_toolkit` configuration; in an environment where the Toolkit is not enabled, as production often is, repeat it in
`kit_subdirectory`.

The Popover recipe brings its Stimulus controller, `popover`, which places the panel next to its button; without it,
the browser's popover API still opens and closes the panel.

`assets/package.json` declares the Stimulus controller `calendar`, `phil--calendar-bundle--calendar`, fetched lazily:
its code is loaded when a month's grid first appears on a page. With AssetMapperBundle registered, the bundle writes in
the `asset_mapper` extension the paths `assets/src` as `@phil/calendar-bundle/` and `assets/styles` as `phil-calendar/`;
without it, it writes nothing. With another pipeline, import `vendor/phil/calendar-bundle/assets/styles/calendar.css`.
The views are described in [views.md](views.md).
