# Testing

| Area | What is covered |
|---|---|
| `Event`, `EventHost`, `Occurrence` | Scheduling, checks, whole days, rules, dates added and exceptions, the end of a series, overlaps, limits, hosts |
| The mapping | The columns, lengths and indexes the migrations create, the venue an event outlives |
| `Venue`, `Venues`, `VenueDraft` | A place of its own and a listing of the directory, limits and refusals, an event at a venue with its poster |
| `Calendar`, `EventDraft` | Drafts on the wall clock of their time zone, hosts, the occurrences of a period in order, of a host or a tag |
| `ICalendar` | Times with a zone, in UTC and as dates, rules, dates added, exceptions, escapes, folding between characters |
| `EventType`, `TagsTransformer` | Submitting, the default time zone, labels and options |
| `CalendarFeedController`, `ICalendarResponse` | The iCalendar file, of a host and of a tag |
| `Period`, `CalendarView` | Each view's bounds, Monday and Sunday weeks, months of five and six weeks, the periods around, the change of time |
| `MonthGrid`, `Lanes`, `TimeGrid`, `Resources` | Bars across weeks and lanes, the order of a lane, what does not fit, times side by side, edges of the hours and of the day, instants, shared days |
| `Agenda`, `DaySpan`, the sources | Groups by days, days in words in French, English and German, the events and the almanac's dates as items |
| `CalendarViews`, `CalendarComponent`, `AlmanacImport` | The defaults, the columns of a shared day, the component's moves, views and fallbacks, the almanac made events once, the command |
| The Stimulus controller | Days and weeks moved inside the grid, the tab order, under Node's test runner |
| `CalendarExtension`, `PhilCalendarBundle` | When an occurrence takes place in two languages; the services, the configuration, the mapping |
| Translations | The tables, limits and refusals of a translation; texts served whole, a locale not served, events in their own text without locales; schema.org data and Twig in a locale |
| Attendees, members' calendars, proposals | Each refusal, the policy asked last, counts and pages, places left; the occurrences a member attends or hosts, once each; the proposal's states and transitions, a guard that holds one |
| The budget, `ExpansionBudget` | Its bounds, what each event costs, an event cut at the occurrences left, the stop logged once, the reset; a request of 1,500 rules that never match, held to a bounded time that grows with the budget and not with the events |
| A member's calendar | The value of each source, following, hiding and forgetting, 200 choices at most, the draft validated; attended, hosted and followed less hidden, in one period and one query of hosts; the secrets of feeds issued, revoked, refused when malformed |
| A member's feed, privacy, the choice form | Occurrences as a file of their own, the private answer, `304`, `404` for a secret revoked, unknown or malformed; the export and an erasure run twice; the form's fields; the component of a member, and its note when the budget cuts the period |
| Pages of their own, printing | Addresses read by `phil/url`, their bound, a new rule that takes them away; the months printed — items across months, a cancelled one, an item that ends at midnight, weekdays from Sunday —, the bounds, the PDF |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, PhilRecurrenceBundle
and the bundle: `CalendarBundleTest` stores events and their hosts, finds them by period, host and tag, asks both
feed, renders the form and the Twig function in `fr_CA`, keeps venues and posters, and renders the component in each
view — with TwigComponentBundle, LiveComponentBundle and StimulusBundle. `CalendarFeaturesTest` translates an event and
its venue and reads them in English and French, signs members up on `DoctrineAttendanceRepository` within a capacity,
reads a member's calendar, approves and refuses a proposal through its workflow, prints a month and follows a page of
its own on the views. `PersonalCalendarTest` keeps choices and secrets in their tables, answers a member's feed at its
address, draws the choices with the recipes of the kit, renders the component of a member, resets the budget with
`services_resetter`, and forgets a member's data. `AssetMapperTest` boots a kernel with AssetMapperBundle and finds the
controller and the stylesheet where the bundle maps them; the other kernel boots without it. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

The integration suite runs the tables and queries of a member's calendar — choices, secret and their moments in UTC,
attendances, proposals, erasure — on MySQL and PostgreSQL, from the URL of each server:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
    vendor/bin/phpunit packages/calendar-bundle/tests/Integration
```

```sh
vendor/bin/phpunit packages/calendar-bundle/tests
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes — among them a member's month of
100 and 500 events, and a month of 1,000 and 5,000 rules that never match, which the budget holds to the same time.
From the monorepo root:

```sh
composer bench -- packages/calendar-bundle/tests/Benchmark
```
