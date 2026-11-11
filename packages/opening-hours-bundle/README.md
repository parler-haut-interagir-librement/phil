# phil/opening-hours-bundle

**An opening hours form, a JSON column and localised hours in Twig, for Symfony.**

phil/opening-hours-bundle brings the hours of `phil/opening-hours` into Symfony. `OpeningHoursType` has a field for
each day, named in the request's language, where the day's ranges are typed as `09:00-12:00, 13:00-17:00`. It adds a
field for special hours, one period a line, and a choice of time zone. A field that cannot be read is invalid on its
own, with a message that shows how to type it. The Doctrine column type `phil_opening_hours` keeps hours in one JSON
column. In Twig, functions say whether the place is open now and when it next opens. Others write the week and the
special hours to come, and give the schema.org `openingHoursSpecification`. A table template shows the week. Labels
and messages are in English and French.

It is for Symfony applications where an editor or an administrator enters a place's hours and visitors read them.
Days, dates and times are written by ICU in the request's language, so the same hours read *lundi – vendredi* for
one visitor and *Monday – Friday* for another.

Outside Symfony, use `phil/opening-hours` on its own. The bundle adds what needs the framework or `ext-intl`: the
form, the column type, the ICU wording, and a PSR-20 clock for "now".

## Status

| Part | State |
|---|---|
| `OpeningHoursType` | Implemented and tested |
| The column type `phil_opening_hours` | Implemented and tested |
| Twig: `opening_hours_open`, `_next`, `_week`, `_specials`, `_schema`; the table template | Implemented and tested |
| Labels and messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, `default_timezone`. |
| [Forms](doc/forms.md) | `OpeningHoursType` and its options. |
| [Doctrine](doc/doctrine.md) | The column type `phil_opening_hours`. |
| [Twig](doc/twig.md) | The functions and the table template. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/opening-hours-bundle
```

```php
#[ORM\Column(type: 'phil_opening_hours', nullable: true)]
private ?OpeningHours $hours = null;

$builder->add('hours', OpeningHoursType::class);
```

```twig
{% include '@PhilOpeningHours/table.html.twig' with {hours: shop.hours} %}
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`; `phil/opening-hours` 0.1; `psr/clock` 1; `symfony/config`,
`symfony/dependency-injection`, `symfony/form` and `symfony/options-resolver` 8.2 or later. Doctrine DBAL and Twig
are optional. With Twig, the container needs a `clock` service, which FrameworkBundle registers once `symfony/clock`
is installed: without it, the container does not compile.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
