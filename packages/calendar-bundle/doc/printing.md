# The calendar printed

With `phil/paged-media` installed, `PrintableCalendar` prints the calendar a month per page, by the library's calendar
template: every item of the views — the events in the reader's locale, the almanac's dates — on each day it covers, its
time first when it has one, a cancelled one marked `×`, the month and the weekdays named by ICU in the reader's
language, the weeks from the configured first day.

```php
$pdf = $printable->document(2026, 11, count: 3, locale: 'fr_CA', tags: ['jeux'])->render();

return new Response($pdf, 200, ['Content-Type' => 'application/pdf']);
```

| Method | Gives |
|---|---|
| `months($year, $month, $count, $locale, $tags)` | The `CalendarMonth`s of the template, 1 to 24 months from a first one |
| `document(...)` | The `Document`, which the application may still draw on before it renders it |
| `weekdays($locale)` | The names of the seven days, from the first day of the week |

The page is a Letter page in landscape, the template's default; a day's square shows what fits, cut with an
ellipsis. The bundle registers no route: the application's controller chooses who may print, and which months.
