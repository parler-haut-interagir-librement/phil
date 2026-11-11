# Text, schema.org and storage

## The text form

`HoursText::day()` reads a day, its ranges separated by commas, nothing for closed. `HoursText::specials()` reads
special hours, one period a line — a day or `from..through`, its ranges, and a label after `#` —, and
`specialsToText()` writes them back:

```text
2026-12-24 09:00-12:00 # Christmas Eve
2026-12-25 # Christmas
2027-07-01..2027-08-31 10:00-16:00
```

Empty lines are skipped; a line that is none of these throws `InvalidOpeningHours`.

## schema.org

`OpeningHoursSchema::of($hours)` gives the `openingHoursSpecification` of a place:

```php
[
    ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'], 'opens' => '09:00', 'closes' => '17:00'],
    ['@type' => 'OpeningHoursSpecification', 'opens' => '00:00', 'closes' => '00:00', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-25'],
]
```

A range of the week is one specification for the days that share it; each range of special hours is one with its
period, and special hours that close the place open and close at `00:00`, as search engines read it.

## Storage

`OpeningHoursArray::from()` writes hours as an array of texts, `to()` reads them back — what
`phil/opening-hours-bundle`'s column type stores as JSON:

```json
{"timezone": "America/Toronto",
 "week": {"1": "09:00-17:00", "6": "10:00-16:00"},
 "specials": [{"from": "2026-12-25", "through": "2026-12-25", "hours": "", "label": "Christmas"}]}
```

Closed days are left out of `week`. Anything else than what `from()` writes throws `UnreadableOpeningHours`.
