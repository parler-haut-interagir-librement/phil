# Extension points

What an application implements, configures or relies on. The rules and the almanac are `phil/almanac`'s.

## `StoredDateRepository`

`Phil\AlmanacBundle\Domain\Repository\StoredDateRepository` — the dates an administrator keeps, which replace the
configured dates of their key. With `database: true`, it is `DoctrineStoredDateRepository`, over `phil_almanac_date`.
See [Stored dates and the form](stored-dates.md).

| Method | Contract |
|---|---|
| `all(): array<string, StoredDate>` | Every stored date, by key |
| `ofKey(string $key): ?StoredDate` | One date |
| `save(StoredDate $date): void` | Keeps it; the next almanac has it |
| `remove(StoredDate $date): void` | Forgets it; the configured date of its key comes back |

A repository of the application's own that keeps what it read resets between requests (`kernel.reset`), as the shipped
one does, so that a worker sees dates saved by another process.

## Configuration

| Option | What it extends |
|---|---|
| `dates` | The dates of the almanac, each a key, a title, any rule of `phil/almanac`, an end or a number of days, and tags |
| `timezone` | The zone every date is computed in |
| `database` | Whether stored dates join, and replace, the configured ones |
| `eclipses` | Whether the solar and lunar eclipses are dates of the almanac, under which titles and tags |
| `places` | The places `sun_times()` knows by name |

See [Configuration](configuration.md).

## Services, forms and Twig

| Point | How |
|---|---|
| The almanac in code | `AlmanacFactory::almanac()`, asked for at each use by a long-lived service rather than kept |
| Which dates are the configuration's | `Definitions::isConfigured()` and `stored()`, for an admin page |
| The form of a stored date | `StoredDateType` and its `key_locked` option; labels in the `phil_almanac` domain, which an application overrides |
| Dates in a page | `almanac_dates(from, until, tags)` — see [Twig](twig.md) |
| The Sun of a place in code | `Places::get($name)`, then `SunTimes::on()` of `phil/almanac`; a place the application keeps elsewhere — a venue's coordinates — is a `Place` given to `sun_times()` |
| Eclipses in a page | `almanac_eclipses(from, until, body)` |
