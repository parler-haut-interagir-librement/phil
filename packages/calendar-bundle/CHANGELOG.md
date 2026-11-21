# Changelog

All notable changes to `phil/calendar-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- The entities `Event` and `EventHost`, the value `Occurrence`, `InvalidEvent` and the port `EventRepository`. `Event`
  keeps its recurrence and the end of its series with what they were read from, and reads them again only once the rule,
  the times, the time zone, the occurrences taken away or the dates added change; the end of an occurrence is worked out
  without reading the event's times again. `Event::repeat()` keeps the occurrences taken away when the rule is the same.
  `Event::occurrences()` reads the rule from just before the period, so the cost of a period does not grow with the
  event's age. `Event::seriesEndsAt()` of a rule that ends on a date, with no count, is read back from that date.
  The columns `starts_at`, `ends_at` and `series_ends_at` have the DBAL type `datetime_utc_immutable`, written and
  read back in UTC whatever the time zone of PHP; the SQL is that of a `DATETIME`, so a table created before needs no
  migration, but rows written while PHP ran in another time zone than UTC are converted once to UTC.
  `Event::addDate()`, `removeDate()` and `addedDates()`: occurrences added as `RDATE` does, 1,000 at most, after the
  start, of the event's length, kept whatever the rule, in the column `added_dates`; the end of the series is that of
  the rule or of the last date added. An event's image refuses a path a browser reads as another host's,
  `/\\host/x.png`, and any backslash, in the entity and in `EventDraft`. Its page and its image are read by `phil/url`
  and kept normalised; an address on an IP or on `localhost` is refused.
- `Calendar`, `EventDraft` and `ICalendar`, which writes an event's dates added as `RDATE`, and schema.org data that
  counts them in its schedule; `ICalendar::ofOccurrences()`, occurrences on their own, each a `VEVENT` of its own times
  and its own `UID`, with the page of its date. `Calendar::events()`: the events with an occurrence in a period, each
  read as far as its first one. Both draw on `ExpansionBudget`, shared by every list of a request — views, feeds, a
  member's calendar, the printed months — and reset between requests by `kernel.reset`: so many occurrences and so many
  repeating events read, `phil_calendar.budget.occurrences` (10,000) and `budget.expansions` (500); past it, the events
  left are not read, `Calendar::isCut()` says so, and the stop is logged once as a warning on the `phil_calendar`
  channel. A request of thousands of rules that never match is held to a bounded time. `Calendar::occurrences()` and
  `ICalendar` gather their lists without copying them for each event, and `ICalendar` keeps the control characters it
  leaves out in a constant.
- Views laid out in PHP, `Application\View`: `CalendarView`, `Period`, `Item` and its sources `EventItems`,
  `AlmanacItems` and `Items`; `MonthGrid`, `TimeGrid` with `Resources`, `Agenda` and `DaySpan`; for a member,
  `CalendarViews` and `Items` take the events of their own calendar, `EventItems::itemsOf()`. The month's grid counts
  each item's days once and gives the item only to the weeks it covers; `Period::dayNumber()` counts without building a
  date, `Item::firstDayNumber()` and `lastDayNumber()` give an item's days as numbers, and `TimeGrid` counts a day's
  number once.
- `DoctrineEventRepository`; `EventRepository::hostsOfEach()`: the hosts of a view's events in one query; the venues
  joined to the events.
- Venues: `Venue` on `phil_calendar_venue` — a place of the calendar's own or a listing of the directory —,
  `VenueRepository` and `DoctrineVenueRepository`, `Venues`, `VenueDraft` and `VenueType`; an event's `venue` and
  `image`, in its draft and its form.
- Tags in the taxonomy: the port `EventTagging`, its adapter `TaxonomyEventTagging` wired when
  `phil/taxonomy-bundle` registers its taxonomy, `Calendar::remove()` and `Calendar::refreshTags()`,
  `EventRepository::all()`, and `phil:calendar:refresh-tags`; a tag is up to 100 characters, as in the taxonomy.
- The almanac's dates among the events, `AlmanacItems`, and made events, `AlmanacImport` and
  `phil:calendar:import-almanac`, with `phil/almanac-bundle`. Both take the almanac or a closure that gives it; the
  bundle passes a closure, so a long-lived worker sees the dates stored since it started. `AlmanacItems` gives no
  item for a period the almanac cannot compute.
- `PhilCalendarBundle`, extension alias `phil_calendar`, keys `default_timezone`, `name`, `first_weekday`,
  `kit_subdirectory`, `budget` and `privacy_member_kind`. With AssetMapperBundle registered, the paths of the controller,
  `@phil/calendar-bundle/`, and of the stylesheet, `phil-calendar/`, are written in the `asset_mapper` extension;
  without it, nothing is. The
  clock is required: `CalendarComponent` and `CalendarFeedController` take a `ClockInterface` before their optional
  arguments, and the bundle wires the container's `clock` service, without falling back to the system's time.
- `EventType` and `TagsTransformer`, which keeps each tag once; the field *Places*, an event's capacity.
- Translations of events and venues: `EventTranslation` and `VenueTranslation` on the translations of
  `phil/doctrine-behaviours`, `EventTexts` and `EventTextSet`, and `phil_calendar.translations.locales`; the views,
  schema.org data and the Twig function `calendar_texts` in the reader's locale, a translation served whole.
- Attendees: `Attendance`, `AttendanceRepository` and `DoctrineAttendanceRepository`, which counts and lists with
  DBAL; `Attendees`, an event's capacity, `AttendanceRefused` and the port `AttendancePolicy`, where paid attendance
  plugs in; `Member`. `created_at` has the DBAL type `datetime_utc_immutable`, like the event's times.
- A member's calendar, `PersonalCalendar`: the occurrences they attend, host and follow, less those they hide, in one
  period and one query of hosts. `CalendarChoices`, `MemberChoices`, `CalendarChoice` on `phil_calendar_choice`,
  `ChoiceSource`, `InvalidChoice` and the port `CalendarChoiceRepository`: the hosts' calendars, tags, venues and events
  a member follows or hides, 200 at most, chosen at a moment in UTC; `CalendarChoiceType`, `CalendarChoiceDraft`, the
  template `calendar/_choices.html.twig` on the Card, Badge and Button recipes, and the Twig function
  `calendar_choices`. `PersonalFeeds`, `PersonalFeedToken` on `phil_calendar_feed_token` and the port
  `PersonalFeedTokenRepository`: a secret per member, kept as its SHA-256 digest, revoked by the next or by
  `revoke()`; `PersonalFeedController` and the route `phil_calendar_personal_ics`, a private answer, `404` for a secret
  malformed, unknown or revoked. `CalendarPrivacy`, with `phil/privacy-bundle`: a member's choices, feed, attendances
  and proposals exported, and erased; `EventRepository::proposalsOf()` and `forgetProposer()`,
  `AttendanceRepository::forgetMember()`, `Event::forgetProposer()`; members' proposals, `Proposals`, on the state machine
  `phil_calendar_proposal` of `symfony/workflow`, `ProposalWorkflow`; a proposal stays out of the calendar until it is
  approved.
- A page of its own for an occurrence, `Event::linkDate()`, followed by the views; the calendar printed a month per
  page, `PrintableCalendar`, by the calendar template of `phil/paged-media`.
- The Twig functions `calendar_personal`, `calendar_attendance`, `calendar_places_left` and `calendar_attends`.
- `CalendarExtension`, which keeps one ICU formatter per language, style, time zone and pattern, 64 at most, and the
  Twig functions `calendar_event_schema`, schema.org data for JSON-LD, and `calendar_event_json_ld`, which adds the
  event to the page's graph of phil/structured-data-bundle when that bundle is installed, and prints its own script
  element otherwise.
- The calendar on a page: the Live Component `PhilCalendar:Calendar` in five views, its templates drawn with the
  Popover, Badge, Card, Button, ButtonGroup and Empty recipes of the PHIL UX kit, named by `phil_calendar_kit()`,
  `KitComponents` and `KitSubdirectoryPass` after the components subdirectory of the Symfony UX Toolkit, the Stimulus
  controller `calendar` and `assets/styles/calendar.css`, with its own text for screen readers; `CalendarViews` and
  `HostLabels`; the Twig filters and functions `calendar_days`, `calendar_title`, `calendar_format`, `calendar_time`,
  `calendar_hour` and `calendar_link`. `CalendarComponent` takes a date more than 100 years from today as today, so a
  crafted `?date=9999-11-01` cannot make every endless event unroll; its prop `member`, not writable, shows a member's
  own calendar, and a note says when the budget of the request cut the period. The controller is fetched lazily.
- `CalendarFeedController` and `ICalendarResponse`, with the routes of `config/routes.php`. The feed gathers the
  events, not their occurrences, answers with an `ETag` of the events and their versions,
  `Cache-Control: public, no-cache`, and `304 Not Modified` to a client that sends it back, and writes a rule's
  `UNTIL` of the start's kind, as RFC 5545 asks: a UTC time next to a timed `DTSTART`, a day next to whole days.
- The labels in English and French, in `translations/`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_calendar.yaml` with the time zone and
  first weekday of a new calendar, and the routes under `/calendar`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
