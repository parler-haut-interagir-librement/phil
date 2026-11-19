# Views

The calendar's views are laid out in PHP, under `Application\View`, so that a template only draws them: a month's
grid, a week or a day by the hour — shared between hosts, venues or tags when asked —, a list grouped by the days the
items cover, and posters. They know nothing of HTML; the `PhilCalendar:Calendar` component draws them on a page
([below](#on-a-page)).

## Period

`Period::of(CalendarView $view, $date, DateTimeZone $zone, int $firstWeekday = 1)` is what a view shows, from a date
in it; `from` is included and `until` not, both midnights in the zone.

| View | `from` – `until` | `previous()`, `next()` |
|---|---|---|
| `Month` | The weeks that hold the month — five or six | A month, keeping the day or the last of a shorter month |
| `Week` | The week that holds the date | Seven days |
| `Day` | The day | One day |
| `List`, `Posters` | The month | A month |

`firstWeekday` is ISO 8601: 1 for Monday, 7 for Sunday. `in($view)` is the same date in another view; `days()` lists
the period's midnights; `holds($day)` says whether a day is in the month the view is about, for the grid's days of
the months around it.

## Items

An `Item` is what a view shows: an occurrence of an event, or a date of the almanac — `id`, `title`, `start`, `end`,
`allDay`, `source` (`event` or `almanac`), `url`, `tags`, `hosts` as `kind:reference`, `venue`, `image`, `description`,
`cancelled`. Whole days end on the midnight of their last day; a time ends at its instant. `firstDay()`, `lastDay()` —
a time that ends at midnight ends the day before — and `spansDays()`; `firstDayNumber()` and `lastDayNumber()` give
those days as `Period::dayNumber()` counts them, days since 1970-01-01.

| Source | Items |
|---|---|
| `EventItems` | The calendar's occurrences, times moved to the period's zone, whole days kept on their dates; the hosts of each event read once |
| `AlmanacItems` | The dates of a `Phil\Almanac\Almanac`, with `phil/almanac`; they link to nothing. The bundle gives it the almanac as a closure, asked for at each period, so that a long-lived worker shows the dates stored since; a period with a year the almanac cannot compute — Easter before 1583 — holds no almanac item |

`Items::of(Period, ?array $tags)` gathers every `ItemSource`, by start — whole days first —, then title, keeping
those with one of the tags when they are given.

## The month

`MonthGrid::of(Period, $items, int $lanes = 3)` gives `MonthWeek`s: their seven `days`, their `bars` and, by column,
the items that did not fit, `hidden`. A `Bar` is an item across the days it covers in the week — `column`, `span`,
`lane`, `continuesBefore`, `continuesAfter` —: split at the end of a week, each in the first lane free, the earliest
and the longest first, whole days before times. Past the lanes shown, an item counts in each of its days' hidden
items — the "+2 more" of a template. `lane($n)` gives a lane's bars, `lanes()` how many lanes the week uses. Each
item's days are counted once and the item given only to the weeks it covers: the layout grows with the items, not with
the items times the weeks.

## The week and the day

`TimeGrid::of(Period, $items, ?Resources $resources, int $firstHour = 0, int $lastHour = 24)` gives a `TimeColumn` per
day — per day and resource when the days are shared —, with its whole-day items and its `TimedBlock`s: `top` and
`height` in percent of the hours shown, `lane` of `lanes` among the items it overlaps, `continuesBefore` and
`continuesAfter`. Without resources, the whole days are `Bar`s across the week, `allDay`. A time outside the hours shown
is pinned to their edge; an instant — a full moon — has no height.

```php
$grid = TimeGrid::of($period, $items, Resources::hosts(['shop:7' => 'La boutique', 'club:2' => 'Le club'], 'Autres'));
```

`Resources::hosts()`, `venues()` and `tags()` take the columns by key with their labels, and the label of a column
for the items that belong to none; without it, those are not shown.

## The list and the posters

`Agenda::of($items)` groups the items by the days they cover, `AgendaGroup`s with their `first` and `last` day: by first
day, then by last day, the shortest first; in a group, whole days first, then by time and title. `items()` gives them
all once, in that order — the posters.

`DaySpan::label($first, $last, $locale, ?int $year)` writes a group's days:

| Days | French | English |
|---|---|---|
| one | mardi 1er septembre | Tuesday, September 1 |
| two | 1er et 2 septembre | September 1 and 2 |
| three or more | 2 au 4 septembre | September 2–4 |
| across months | 30 août au 2 septembre | August 30 – September 2 |
| another year | 15 et 16 mars 2027 | March 15 and 16, 2027 |

The year is written when the days are not all in the year of reference — the period's —, after the last day, and after
the first too when the two are in different years. Another language writes each day as ICU's patterns for it do,
joined by a dash: *1. September – 2. September*.

## On a page

`CalendarViews` is the service a page uses: `period($view, $date)` in the calendar's time zone — `default_timezone` —
and weeks — `first_weekday` —, `zone()`, and `month()`, `time()` and `agenda()` of a period, filtered by tags, of a
member's own calendar when one is given, with `time()` sharing the days between `hosts`, `venues` or `tags`.
`resources()` gives those columns: the hosts and tags the items have, every venue of the calendar. The application
names its hosts by implementing `HostLabels`; a host it does not name shows as its `kind:reference`.

`Items` gathers the services tagged `phil_calendar.item_source`: `EventItems`, and `AlmanacItems` when
`phil/almanac-bundle` registers its almanac. `EventItems` is tagged before `AlmanacItems`, so that of two items that
start together with the same title the calendar's event comes first. An application adds its own source by tagging an
`ItemSource`, with `before` or `after` naming the source it goes before or after. For a member, `Items::of()` asks
`EventItems::itemsOf()`, the occurrences of their own calendar, and the other sources as they are.

### The component

With Symfony UX Live Component, `<twig:PhilCalendar:Calendar />` is the calendar on a page:

| Prop | Default | |
|---|---|---|
| `view` | `month` | `month`, `week`, `day`, `list` or `posters`; in the address |
| `date` | today | The date the period holds, `Y-m-d`, in the calendar's time zone; in the address |
| `tags` | `[]` | Only the items with one of these tags; in the address |
| `views` | all five | The views offered, in their order; a view not offered shows as the first |
| `shared` | none | `hosts`, `venues` or `tags`: the week and the day shared between them |
| `firstHour`, `lastHour` | `0`, `24` | The hours the week and the day show |
| `lanes` | `3` | The lanes a week of the month shows before its "more" |
| `member` | none | `kind:reference`: that member's own calendar instead of the whole calendar |

```twig
<twig:PhilCalendar:Calendar view="week" :views="['week', 'day', 'list']" shared="venues" :firstHour="8" :lastHour="23" />
```

Given a `member`, the component shows that member's own calendar — what they attend, host and follow, less what they
hide ([Members](members.md#a-members-calendar)) —; the other sources, the almanac's dates among them, stay as they are.
The page sets it from who is signed in; it is not writable, and Live Component refuses a request that changes it. A
value that names no member is an `InvalidEvent`.

```twig
<twig:PhilCalendar:Calendar view="list" member="member:{{ app.user.id }}" />
```

When the request's [budget](events.md#the-budget-of-a-request) leaves events of the period out, a note says so under
the view, `calendar.cut`, in a `role="status"` paragraph, `phil-calendar__cut`.

Its toolbar moves to the period before and after and back to today, and switches views, without reloading the page —
the actions `previous`, `next`, `today` and `show`. **Every button is a link** to the same period in the address,
`?view=week&date=2026-09-15`: a browser without JavaScript, a reload, a shared link show the same period. An
unknown view, a date that cannot be read, or one more than `CalendarComponent::YEARS` (100) years from today falls back
on the first view offered and on today: the date in the address is anyone's to write.

The templates are `templates/components/Calendar.html.twig` and `templates/calendar/_toolbar`, `_month`, `_time`,
`_list`, `_posters` and `_item`, in the `@PhilCalendar` namespace, which an application overrides as any bundle's.
They draw the recipes of the PHIL UX kit — `Popover`, `Badge`, `Card`, `Button`, `ButtonGroup` and `Empty`, named by
`phil_calendar_kit()` ([installation](installation.md)) — and classes of their own, `phil-calendar__*`. An item is
the trigger of a popover that opens its details — when, where, its description, tags and page —, on the browser's
popover API; a day of the month opens what did not fit the same way. The toolbar's links are the Button recipe drawn
as links, in two button groups; a poster is a card, its title the link that covers it; a period without items is an
empty state.

### Accessibility

Each day of the month is a link that names the whole date for screen readers and opens the day; the row of weekdays
is hidden from them. The Stimulus controller `calendar` makes the days one stop of the tab order: the arrows move a
day or a week — mirrored right to left —, Home and End to the ends of the week, Page Up and Page Down, or an arrow
past the first or the last day, to the period before or after; a key with Alt, Ctrl or Meta is left to the browser.
Today is `aria-current="date"`, the view shown `aria-current="true"`; the title is announced when the period changes;
the arrows of the toolbar and a cancelled item have a text for screen readers. Without the controller, every day,
link and button is in the tab order.

Dates are written in the calendar's time zone, whatever Twig's: the templates format them with `|date(…, false)`.

### The look

`assets/styles/calendar.css` is written on PHIL UX's tokens, with a value of its own for each when PHIL UX is not
loaded, in the `phil.components` layer: an application's styles win. Its text for screen readers alone,
`phil-calendar__hidden`, needs no utility of another stylesheet. `--phil-calendar-event` and
`--phil-calendar-almanac` colour the items by source, `--phil-calendar-hour-height` sets the height of an hour.

### Words

| Twig | Gives |
|---|---|
| `calendar_days(first, last, year)` | `DaySpan::label()` in the request's language |
| `period\|calendar_title` | *septembre 2026*, the days of a week, a day |
| `moment\|calendar_format(pattern)` | A moment written with an ICU pattern: `EEE`, `HH:mm` |
| `moment\|calendar_time`, `hour\|calendar_hour` | A time, an hour, as the language writes them short |
| `calendar_link(view, date, tags)` | The address of a view and a date, for the links |

The words are written by ICU with one formatter for each language, style, time zone and pattern, built once and kept by
the extension: a month of hundreds of items formats them with a few. It keeps 64 formatters at most and builds them
again past that, so that languages and time zones taken from requests cannot grow the memory of a worker that lives
across requests.

## The almanac made events

`AlmanacImport::import($year, ?array $tags)` makes the almanac's dates of a year events of the calendar, for those an
application wants to change, describe or host like any other: each a whole-day event — a phase, a season or an eclipse
on its day —, hosted by `almanac:<key>`, made once however many times the year is imported. It returns how many it made
and how many already had theirs. `phil:calendar:import-almanac 2027 --tag=holiday` does it from the console.
