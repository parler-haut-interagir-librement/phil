# Extension points

What an application configures, extends or overrides. The hours are `phil/opening-hours`'s.

## Configuration

| Option | What it extends |
|---|---|
| `default_timezone` | The time zone of new hours — see [Installation](installation.md) |

## Forms

| Point | How |
|---|---|
| The time zone of one field | The `timezone` option of `OpeningHoursType`, and `with_timezone` to let people choose it |
| The language of the days | Its `locale` option |
| More than the form does | A form type extension of the application's on `OpeningHoursType` |

See [Forms](forms.md).

## Storage and Twig

| Point | How |
|---|---|
| Where hours are kept | The `phil_opening_hours` column type, JSON through `OpeningHoursArray` — see [Doctrine](doctrine.md) |
| Open now, next opening, the week | `opening_hours_open`, `opening_hours_next`, `opening_hours_week`, `opening_hours_specials` and `opening_hours_schema` — see [Twig](twig.md) |
| The table of hours | `@PhilOpeningHours/table.html.twig`, overridden in `templates/bundles/PhilOpeningHoursBundle/table.html.twig` as any bundle template, or replaced by a layout of the application's over `opening_hours_week` |
