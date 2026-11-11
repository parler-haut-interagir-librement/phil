# Twig

| Function | Gives |
|---|---|
| `opening_hours_open(hours, at = now)` | Whether the place is open; `false` for `null` |
| `opening_hours_next(hours, at = now)` | When it next opens, in its time zone, or `null` |
| `opening_hours_week(hours, locale = null)` | Rows of `days`, `lundi – vendredi`; `heading`, the same with only its first letter a capital, `Lundi – vendredi`, `Monday – Friday`; and `hours`, `09 h 00 – 17 h 00`, or `null` when closed |
| `opening_hours_specials(hours, at = now, locale = null)` | The special hours not over yet on the place's calendar, `at` read in its time zone: `period`, `label`, `hours` or `null` when closed |
| `opening_hours_schema(hours)` | The `openingHoursSpecification`, to put in a place's JSON-LD |

Days, dates and times are written by ICU in the request's language, or the one given; the end of the day is written
as midnight. Twig's `capitalize` filter would lower every letter after the first, `Monday – friday`: a row's
`heading` is the one to put at the start of a line. The formatters are built once per language and style, and kept.

## The table template

```twig
{% include '@PhilOpeningHours/table.html.twig' with {hours: shop.hours} %}
```

A table of the week, and one of the special hours to come, *Fermé* or *Closed* where the place is.
