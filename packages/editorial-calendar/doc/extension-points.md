# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Implemented by the application

| Port | What it provides |
|---|---|
| `Contract\TransitionReader` | The transitions planned elsewhere between two moments, shown beside the items; `NoTransitions` by default |

## Configured by the application

| Type | What it sets |
|---|---|
| `Review\ReviewRules` | The minimum spacing between publications and the maximum gap without any |
| `ICalendar\ICalendarWriter` | The domain of the event identifiers, and the name of each stream |
| `View\CalendarView::week()` | The first day of the week |
| `Filter` | The authors, sections and states a view shows |

## Relied on

- The calendar, its items and its batches are immutable: each change gives a new value, and a batch is applied whole
  or not at all.
- Moments are kept in UTC; views cut them in the time zone they are given.
- An author is a stable identifier, never an email address.
- `CalendarView`, `Review` and `ICalendarWriter` keep no state between calls: one instance serves every request and
  every worker.
- The classes of the package are final; `Moment` is the package's own.
