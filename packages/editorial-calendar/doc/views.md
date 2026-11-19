# Views

`CalendarView` shows a calendar between two moments. Each view gives a `Period`: the UTC moments it starts at,
included, and ends at, excluded, its entries in their order, and, for a day, a week or a month, the same entries day by
day.

```php
$view = new CalendarView($transitions);
$zone = new DateTimeZone('America/Toronto');

$view->day($calendar, $date, $zone);
$view->week($calendar, $date, $zone);      // from Monday
$view->week($calendar, $date, $zone, 7);   // from Sunday
$view->month($calendar, $date, $zone);
$view->list($calendar, $from, $to);        // entries only, no days
```

## Days and time zones

A day, a week or a month is cut in the time zone given: each `Day` holds its local date (`Y-m-d`) and the UTC moments
it starts and ends at, 23 or 25 hours apart on a change of time. A week starts on the day numbered 1 for Monday, as
ISO 8601 numbers them, to 7 for Sunday; another number throws `InvalidItem`. A list ends after it starts.

## Entries

An `Entry` is one line at a UTC moment:

| `kind` | What it shows |
|---|---|
| `EntryKind::Batch` | A batch, with all its items that are not withdrawn, as one entry |
| `EntryKind::Item` | An item planned alone, with a moment |
| `EntryKind::Transition` | A transition planned elsewhere, read through the `TransitionReader` |

Entries are ordered by moment, then batches, items and transitions, then by `key`: the identifier of the item or the
batch, or the resource and the transition. Withdrawn items are left out; published items stay, at the moment they were
published. Ideas and drafts without a moment are not in time: `EditorialCalendar::unscheduled()` lists them, and
`manualBatches()` the batches applied by hand.

## Filters

A `Filter` keeps the items of some authors, some sections or some states; each view, `list()` included, and
`EditorialCalendar::unscheduled()` take one as their last argument. A criterion left empty accepts every item, and an
item is shown when it passes all of them: `byAuthors()`, `bySections()` and `byStates()` each replace their own
criterion and keep the others.

```php
use Phil\EditorialCalendar\Filter;

$filter = Filter::all()
    ->byAuthors(new AuthorId('user:7'))
    ->bySections('news', 'events')
    ->byStates(ItemState::Ready, ItemState::Scheduled);

$view->week($calendar, $date, $zone, filter: $filter);
```

An item without a section passes no section criterion. A batch is shown when one of its items passes, with only the
items that pass. Transitions planned elsewhere have no author, section or state: a filter that is not empty leaves them
out, and `Filter::all()`, the default, keeps them.

## Transitions planned elsewhere

A site plans more than publications: a page unpublished at the end of a campaign, registrations opened at a date. The
calendar reads them through its port `TransitionReader`, whose `between($from, $to)` gives `PlannedTransition` values —
a `ResourceReference`, the name of the transition and its moment —, and shows them beside its items. The application
implements the port over its scheduler; `NoTransitions`, the default, shows the calendar's own items only. A transition
the reader gives outside the range is left out.
