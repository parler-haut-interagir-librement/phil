# Installation

```sh
composer require phil/editorial-calendar
```

The library needs PHP 8.5 or later with `ext-mbstring`, and `phil/resource`, whose `ResourceReference` names the
content an item publishes. It has no other runtime dependency.

The calendar and its items are immutable values the application stores as it likes; `CalendarView`, `Review` and
`ICalendarWriter` keep no state between calls, so one instance of each serves every request and every worker.

```yaml
# config/services.yaml
services:
    Phil\EditorialCalendar\View\CalendarView:
        arguments:
            $transitions: '@App\Calendar\SchedulerTransitions'   # an implementation of TransitionReader
    Phil\EditorialCalendar\Review\Review:
        arguments:
            $rules: !service { class: Phil\EditorialCalendar\Review\ReviewRules, arguments: [3600, 172800] }
    Phil\EditorialCalendar\ICalendar\ICalendarWriter:
        arguments: ['example.org']
```
