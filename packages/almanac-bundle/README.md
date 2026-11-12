# phil/almanac-bundle

**Holidays and computed dates declared in configuration, editable by an administrator, shown in Twig.**

phil/almanac-bundle brings `phil/almanac` into a Symfony application. Dates are declared in configuration, such as
*Thanksgiving, 2nd monday of october* or *Mardi Gras, easter - 47 days*, and computed in the time zone the
configuration gives. The almanac is a service, built from those dates each time it is asked for. `almanac_dates`
gives the dates of a period in Twig, `sun_times` the sunrise, sunset and twilights of a configured place, and
`almanac_eclipses` the solar and lunar eclipses, which `eclipses: true` also declares as dates. Labels and messages
come in English and French.

It is for a site that shows holidays and seasonal dates next to its own content. When the application wants it, dates
are also kept in a Doctrine table, so an administrator adds or changes one through `StoredDateType` without a
deployment. A stored date replaces the configured date of the same key. A row whose rule cannot be read is left out,
so one bad row does not stop every page. Reach for `phil/almanac` alone outside Symfony. `phil/calendar-bundle` shows
these dates among its events.

The stored dates sit behind the `StoredDateRepository` port, with `DoctrineStoredDateRepository` as its adapter,
switched on by `database: true`. Doctrine ORM and Twig are optional: the Twig function is registered only when Twig is
installed.

## Status

| Part | State |
|---|---|
| `phil_almanac`: time zone, dates read at build | Implemented and tested |
| Stored dates: `StoredDate`, `DoctrineStoredDateRepository`, replacing configured ones | Implemented and tested |
| The service `Phil\Almanac\Almanac` | Implemented and tested |
| `StoredDateType` and `DateDraft` | Implemented and tested |
| Twig: `almanac_dates` | Implemented and tested |
| Eclipses declared as dates; places; Twig: `sun_times`, `almanac_eclipses` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | The time zone, the dates, the eclipses and the places. |
| [Stored dates and the form](doc/stored-dates.md) | The table, the repository, the form and its validation. |
| [Twig](doc/twig.md) | `almanac_dates`, `sun_times`, `almanac_eclipses`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
phil_almanac:
    timezone: America/Toronto
    dates:
        thanksgiving: { title: Action de grâce, rule: 2nd monday of october, tags: [holiday] }
        full-moon: { title: Pleine lune, rule: full moon, all_day: false }
```

```twig
{% for date in almanac_dates('2026-10-01', '2026-11-01') %}
    {{ date.definition.title }} — {{ date.span.start|date('Y-m-d') }}
{% endfor %}
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/almanac` 0.1; Symfony 8.2's config, dependency injection, form, HTTP kernel, options resolver
and validator. Doctrine ORM with `symfony/uid`, for stored dates, and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
