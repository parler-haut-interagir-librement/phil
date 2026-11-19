# Events

## `Event`

An event has a title, up to 200 characters; a description, a place up to 255 characters and an http or https page; a
start and an end on the wall clock of its time zone, kept in UTC; or whole days, from the first through the last.

```php
$event = Event::schedule('Soirée jeux', $start, $end, timezone: 'America/Toronto');
$event = Event::schedule('Festival', new DateTimeImmutable('2026-07-10'), new DateTimeImmutable('2026-07-12'), allDay: true);

$event->rename('Soirée jeux de société');
$event->describe('Apportez vos jeux.', 'Café X', 'https://example.com/jeux');
$event->reschedule($start, $end, allDay: false, timezone: 'Europe/Paris');
$event->repeat(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU'));  // null: it no longer repeats
$event->addDate($anotherStart);                                    // as RDATE
$event->except($occurrenceStart);                                  // as EXDATE
$event->tag(['jeux', 'soirée']);                                   // each once, trimmed
$event->cancel();                                                  // still shown, marked cancelled
```

An event that ends before it starts, an unknown time zone, a title too long or a page of another scheme is an
`InvalidEvent`. The page is read by `phil/url` as a browser reads it and kept normalised — `HTTPS://Example.com:443/a`
is `https://example.com/a` —; an address on an IP or on `localhost` is refused, and the `InvalidUrl` that says why is
the exception's previous one. A new rule clears the occurrences taken away; the same rule given again — an edit through
the form that leaves the schedule alone — keeps them. The occurrences keep the first one's time of day, on the wall
clock of the event's time zone: across a change of time, 19 h stays 19 h.

`addDate($start)` adds an occurrence that starts at a moment after the event's start, of the event's length, as
`RDATE` does: an event of a few dates needs no rule, and a rule gains a date it does not give. A whole-day event adds
the day the moment falls on in its time zone. A date added is kept whatever rule the event repeats by, merged with its
occurrences in order, once even when the rule gives it too; `except()` takes it away as any occurrence, and
`removeDate()` takes it back. A date the event's new start passes is dropped. An event has 1,000 dates added at most —
`Event::ADDED_DATES`, as many as one `RDATE` line holds —; `addedDates()` lists them in the event's time zone.

`occurrences($from, $through, $limit = 1000)` gives the occurrences that overlap a period, in order: one for an event
that does not repeat. A whole-day occurrence lasts through the end of its last day. The rule is read from just before
the period, not from its first occurrence: a month of an event repeating for thirty years costs what a month of a new
one does. `seriesEndsAt()` is when the last occurrence ends, `null` for an endless rule: the repository finds the events
of a period by it. A rule that ends on a date is read back from that date, its last year first, so that saving a long
series costs its last years. The end of the series is that of the rule or that of the last date added, whichever is
later. The recurrence and the end of the series are kept with what they were read from — the rule, the times, the time
zone, the occurrences taken away, the dates added — and read again once one of those changes: an edit that reschedules
and repeats by the same
rule reads the series once.

## A page of its own, and the capacity

`linkDate($occurrence, $url)` gives the occurrence that starts at a moment a page of its own — where its tickets are
sold —, read and normalised by `phil/url` as the event's page is; null takes it away. `urlOn($start)` is that page, or
the event's; the views link each occurrence to it. An event gives a page of its own to 500 occurrences at most, and a
new rule takes them away with the occurrences taken away. `dateLinks()` lists them by the start of their occurrence.

`limitTo($capacity)` sets how many people can attend each occurrence, 1 to 1 000 000, or none. See
[Attendees, proposals and members' calendars](members.md).

## `Occurrence`

An event, a start and an end. `id()` is the event's identifier and the start in UTC, `…@20261111T000000Z`: the same
from one request to the next.

## Venues and images

A `Venue` is where events take place: a place of the calendar's own — a bar, a room with no page of its own on the
site — or a listing of the application's directory, named by a kind and a reference as hosts are, `listing()` as
`kind:reference`. It has a `name`, a `room`, an `address` and `coordinates()`, latitude and longitude both or neither;
`label()` is its name and its room after a dash, *Salle Le Royal — salle B*. `Event::placeAt()` sets an event's venue
— `location()` stays for a place said in words —, and removing a venue leaves its events without one.

`Venues` creates and changes venues from a `VenueDraft` — `VenueType` is its form —, removes them, lists them by name,
and `ofListing($kind, $reference, $name)` gives the venue of a listing, made from its name the first time: the
directory's places become the calendar's without being typed twice. `VenueRepository` is the port,
`DoctrineVenueRepository` its adapter.

```php
$venue = $venues->ofListing('larp-site', '12', 'Terrain du Nord');
$draft->venue = $venue;
$draft->image = '/affiches/salon.jpg';
$calendar->schedule($draft);
```

An event's `image()` — its poster — is an `http` or `https` address or a path from the site's root, 2048 characters at
most, set by `illustrate()`. A path that a browser would read as another host's — `//host/x.png`, `/\host/x.png`, or
any backslash — is refused, by the entity and by the draft's validation alike. An address is read and normalised by
`phil/url`, as the page is. The posters view shows it.

## Hosts and tags

A host is whoever holds the event: a kind and a reference of the application's own, each 1 to 64 letters, digits,
`_`, `.`, `:` or `-` — `association` and `42`, `shop` and `7`. An event has any number of hosts, an `EventHost` each,
stored apart: the event never loads them.

Tags are free words, up to 100 characters each, kept in the event's row. The calendar filters on them as written:
`jeux` is not `Jeux`.

### Tags in the taxonomy

With `phil/taxonomy-bundle` registered, an event's tags are the taxonomy's, shared with the rest of the site: the
port `EventTagging` and its adapter `TaxonomyEventTagging`, where an event is the resource `phil_calendar_event`.
Wired by themselves — nothing to configure.

- `Jeux de rôle` and `jeux de role` are one tag: the event keeps the name the taxonomy knows, the first one given.
- A name the taxonomy makes no slug of — punctuation alone — is left out.
- The event keeps a copy of the names in its row, for the views to filter on without asking the taxonomy for each
  event. After tags are renamed or merged in the taxonomy, `phil:calendar:refresh-tags` — `Calendar::refreshTags()` —
  gives the events the new names; until then, a filter by the new name finds nothing. An event tagged before the
  taxonomy came gives it its own words. The command reads every event: run it from the console.
- The event is saved first, then its tags given to the taxonomy, and saved again only when the taxonomy names them
  otherwise.
- `Calendar::remove()` removes the event, then takes its tags away from the taxonomy; an event removed through the
  repository alone leaves them.

```php
$taxonomy->taggedWith($taxonomy->tag('jeux de rôle'), TaxonomyEventTagging::RESOURCE); // the events, as resources
```

## `Calendar`

The service an application talks to.

| Method | What it does |
|---|---|
| `schedule(EventDraft)` | A new event, saved |
| `apply(Event, EventDraft)` | Changes an event, saved |
| `remove(Event)` | Removes it, its hosts, and its tags from the taxonomy |
| `refreshTags()` | Gives the events the names of their tags in the taxonomy again; how many changed |
| `host(Event, kind, reference)`, `unhost(…)` | Adds a host once; takes it away |
| `occurrences(from, through, ?kind, ?reference, ?tag)` | The occurrences of a period, by start then title, of one host or one tag |
| `events(from, through, ?kind, ?reference, ?tag)` | The events with an occurrence in the period, each read as far as its first one |
| `isCut()` | Whether the budget of the request left events out since it was last reset |

A draft's start and end are read on the wall clock of the draft's time zone: *19:00* in `America/Toronto` is 19 h in
Toronto, whatever the time zone of the `DateTimeImmutable` the form gave. An event keeps that wall clock and its time
zone, its start and end kept in UTC; these and the moments the bundle records for itself — an attendance, a member's
choice, the issue of a feed's secret — are columns of type `datetime_utc_immutable`, written and read back in UTC
whatever the time zone of PHP or of the database.

### The budget of a request

Every list of occurrences of a request — the views, the feeds, a member's calendar, the printed months — draws on one
`ExpansionBudget`: so many occurrences, `budget.occurrences`, 10 000 by default, and so many repeating events read,
`budget.expansions`, 500 by default. A repeating event costs one expansion each time it is read, whatever it gives: a
rule is walked up to the bounds of `phil/recurrence` even when no occurrence falls in the period, so a calendar of many
rules that never match — `FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30` — costs time without showing anything. An event that does
not repeat costs no expansion.

Past either limit, `Calendar` reads no more events: the list holds what was read, in order, the page renders, and the
stop is logged once per request as a warning, on the `phil_calendar` channel with Monolog, with what was spent. An
event that fills the occurrences left is cut short. `isCut()` says so, and the component prints a note under its view.
A thousand such rules, read for a year's view and a feed, take about 2 s on a laptop without the budget and 0.6 s with
its defaults; the time grows with the budget, not with the events.

The bundle registers one budget, reset between the requests of a worker by `kernel.reset`. A console command that
calls the calendar in a loop resets it between its units of work: `$budget->reset()`. A `Calendar` built by hand
without a budget gives each call one of the default limits.

```yaml
phil_calendar:
    budget:
        occurrences: 10000   # 1 to 1 000 000
        expansions: 500      # 1 to 1 000 000
```

## The repository

`EventRepository` is the port; `DoctrineEventRepository` its Doctrine adapter, aliased to the port. `all()` gives
every event by its start, for `refreshTags()`. `overlapping()`
keeps the events that start by the end of the period and whose series has not ended at its start, of a host when
one is given; `Calendar` then unrolls their rules.
