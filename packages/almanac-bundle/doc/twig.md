# Twig

| Function | Gives |
|---|---|
| `almanac_dates(from, until, tags)` | The `ComputedDate`s that touch the period, its end excluded, by start then title; `tags` keeps those with one of them |
| `sun_times(place, day = 'today')` | The `SolarDay` of a place — a name of `phil_almanac.places`, or a `Place` — on a day |
| `almanac_eclipses(from, until, body = null)` | The `SolarEclipse`s and `LunarEclipse`s whose greatest falls in the period, its end excluded, in order; `'solar'` or `'lunar'` keeps one body |

`from` and `until` are dates — any `DateTimeInterface`, a mutable `DateTime` too —, taken as the instants they are, or
text that `DateTimeImmutable` reads in the almanac's
time zone: `'2026-10-01'` is its midnight there, whatever PHP's default time zone.
Each date has its `definition` — `key`, `title`, `tags` — and its `span` — `start`, `end`, `allDay`, `days()`.

```twig
<ul>
    {% for date in almanac_dates('now', '+1 month', ['holiday']) %}
        <li>{{ date.definition.title }}, {{ date.span.start|date('j F') }}</li>
    {% endfor %}
</ul>
```

## The Sun of a place

`sun_times()` reads text in the place's time zone — `'today'` is the place's today — and a date as the instant it is.
Its `SolarDay` has `noon`, `sunrise()`, `sunset()`, `dawn()` and `dusk()` — the civil twilight by default, or
`Twilight::Nautical` and `Twilight::Astronomical` —, and `dayLength()` in seconds. Under the midnight sun or in the
polar night `sunrise()` and `sunset()` are null: a template checks them. Every instant is in the place's time zone;
`date('H:i', false)` keeps it.

```twig
{% set day = sun_times('montreal') %}
{% if day.sunrise %}
    <p>Sunrise {{ day.sunrise|date('H:i', false) }}, sunset {{ day.sunset|date('H:i', false) }}.</p>
{% endif %}
```

A place the configuration does not have is an `InvalidArgumentException` that lists those it has.

## Eclipses

```twig
{% for eclipse in almanac_eclipses('now', '+2 years') %}
    <li>{{ eclipse.kind.value }} {{ eclipse.at|date('j F Y, H:i') }}</li>
{% endfor %}
```

A `LunarEclipse` also has its phases: `penumbral`, `partial` and `total`, each a start and an end, or null. Declared
with `eclipses: true`, they are dates of the almanac too, among the others of `almanac_dates()`.

`phil/calendar-bundle` shows the same dates among its events.
