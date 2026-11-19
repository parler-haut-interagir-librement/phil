# phil/calendar-bundle

**Events, repeating or not, shown as a month, week, day, list or posters.**

phil/calendar-bundle is a calendar for a Symfony application. Events live in Doctrine with their time zone, whole days,
a recurrence rule and the occurrences it takes away, hosts, tags, a venue and a poster. A venue is a place of its own or
a listing of the application's directory. The bundle gives the occurrences of a period in order, for one host or one
tag, an event form, and an iCalendar file at `/calendar.ics` that people subscribe to. Events and venues are translated
in the locales an application declares. Members attend an occurrence or a whole event, within its capacity; they propose
events that a moderator approves; each has a calendar of their own, made of what they attend, host and follow — hosts'
calendars, tags, venues, events — less what they hide, with a private iCalendar feed at a secret address they can
revoke. An occurrence can have a page of its own, where its tickets are sold, and the calendar prints a month per page.
Every list of occurrences of a request shares one budget, so that a calendar of many repeating rules holds a page to a
bounded time. Labels come in English and French.

It is for a site where organizers or editors schedule events and visitors browse them. The calendar on a page is the
`PhilCalendar:Calendar` Live Component, in five views: month, week, day, list and posters. The week and the day can
share their hours between hosts, venues or tags. When `phil/almanac` is installed, its dates show among the events.
When `phil/taxonomy-bundle` is installed, tags are shared with the rest of the site. Reach for `phil/recurrence` or
`phil/recurrence-bundle` alone when an application needs repeating rules without a whole calendar.

The bundle builds on `phil/recurrence` and `phil/recurrence-bundle`, Doctrine ORM and Symfony Form. Symfony UX Live
Component, Stimulus and Twig are optional. The views are laid out in PHP and know nothing of HTML: the component's
templates only draw what they compute, with the Popover, Badge, Card, Button, ButtonGroup and Empty recipes of the
PHIL UX kit, installed in the application by the Symfony UX Toolkit. Synchronizing with the calendars of members —
CalDAV, Google, Microsoft — is the work of `phil/calendar-sync`. The `Calendar` service sits on the `EventRepository` port,
with `DoctrineEventRepository` as its adapter.

## Status

| Part | State |
|---|---|
| `Event`, `EventHost`, `Occurrence` and the repository port | Implemented and tested |
| `Calendar`: schedule, change, remove, host, the occurrences of a period | Implemented and tested |
| Tags in `phil/taxonomy-bundle`: `EventTagging`, `TaxonomyEventTagging`, `phil:calendar:refresh-tags` | Implemented and tested |
| `DoctrineEventRepository` | Implemented and tested |
| `EventType` and `EventDraft` | Implemented and tested |
| `ICalendar` (RFC 5545) and the feed `calendar.ics` | Implemented and tested |
| The component `PhilCalendar:Calendar`, its templates, its Stimulus controller and its stylesheet | Implemented and tested |
| The almanac's dates among the events, and made events: `AlmanacItems`, `AlmanacImport`, `phil:calendar:import-almanac` | Implemented and tested |
| Twig: `calendar_occurrences`, `occurrence_when` | Implemented and tested |
| Translations of events and venues: `EventTranslation`, `VenueTranslation`, `EventTexts` | Implemented and tested |
| Attendees and capacity: `Attendance`, `Attendees`, `AttendancePolicy`, `DoctrineAttendanceRepository` | Implemented and tested |
| A member's calendar, `PersonalCalendar`; proposals and their workflow, `Proposals` | Implemented and tested |
| What a member follows and hides, `CalendarChoices`, its form and template; their feed at a secret address, `PersonalFeeds` and `phil_calendar_personal_ics` | Implemented and tested |
| A member's data in the export and the erasure of `phil/privacy-bundle`, `CalendarPrivacy` | Implemented and tested |
| The budget of a request, `ExpansionBudget`: occurrences and repeating events read, the stop logged | Implemented and tested |
| A page of its own for an occurrence; the calendar printed, `PrintableCalendar` | Implemented and tested |
| Views laid out in PHP: periods, the month's grid, the week and the day by the hour shared between resources, the list grouped by days, the posters, days in words | Implemented and tested |
| The controller and the stylesheet mapped for AssetMapperBundle, the controller fetched lazily | Implemented and tested |
| Ticketing: selling the places of an occurrence | Outside the package: an application's `AttendancePolicy` and the commerce packages |
| Federation with other servers, ActivityPub | Outside the package: `calendar-activitypub`, a package of its own to come |
| "Add to calendar" links of an occurrence | Waiting for a stable Symfony UX CalendarLink; the iCalendar feeds are the bundle's |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the tables. |
| [Events](doc/events.md) | `Event`, its occurrences, hosts and tags; `Calendar` and the budget of a request. |
| [Forms and Twig](doc/forms-twig.md) | `EventType`, `EventDraft`, the Twig function and filter. |
| [Translations](doc/translations.md) | Events and venues in the locales declared, written and read. |
| [Attendees, proposals and members' calendars](doc/members.md) | Attendance and capacity, paid attendance, a member's calendar, what they follow and hide, their feed and their data, proposals and their workflow. |
| [The calendar printed](doc/printing.md) | A month per page in PDF. |
| [Views](doc/views.md) | Periods, items and their sources, the month, the week and the day, the list; the component, its look and its accessibility; the almanac. |
| [The feed](doc/feeds.md) | The routes and the iCalendar files: the calendar's and a member's. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/calendar-bundle
```

```php
$event = $calendar->schedule($draft);          // an EventDraft, from EventType
$calendar->host($event, 'association', '42');

foreach ($calendar->occurrences($from, $through, 'association', '42') as $occurrence) {
    // $occurrence->event, $occurrence->start, $occurrence->end
}
```

```twig
<twig:PhilCalendar:Calendar view="month" />

{% for occurrence in calendar_occurrences(from, through) %}
    {{ occurrence.event.title }} — {{ occurrence|occurrence_when }}
{% endfor %}
```

## Requirements

PHP 8.5 or later with `ext-intl`, `ext-mbstring` and `ext-sodium`; `phil/recurrence`, `phil/recurrence-bundle`, `phil/url`,
`phil/doctrine-behaviours` and `phil/locale-dimensions` 0.1; Doctrine ORM 3.7 and DBAL 4.5; Symfony 8.2's dependency
injection, config, form, HTTP foundation, UID, validator and workflow; PSR-20's clock and PSR-3's logger. Twig,
Symfony UX Live Component with Twig Component and the Stimulus bundle with the recipes of the PHIL UX kit, AssetMapper,
the console, `phil/almanac`, `phil/paged-media`, `phil/privacy-bundle` and `phil/taxonomy-bundle` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
