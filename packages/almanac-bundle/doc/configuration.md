# Configuration

```yaml
phil_almanac:
    timezone: America/Toronto        # the dates are computed in it
    database: false                  # true: also read phil_almanac_date
    dates:
        thanksgiving:
            title: Action de grâce
            rule: 2nd monday of october
            tags: [holiday]
        mardi-gras:
            title: Mardi gras
            rule: easter - 47 days
        oktoberfest:
            title: Oktoberfest
            rule: saturday on or after 09-15
            until: sunday on or after 10-03
        engineers-week:
            title: Semaine du génie
            rule: monday on or before 02-22
            days: 7
        full-moon:
            title: Pleine lune
            rule: full moon
            all_day: false           # shown at its instant
    eclipses:                        # declares solar-eclipse and lunar-eclipse
        enabled: true
        solar_title: Éclipse solaire
        lunar_title: Éclipse lunaire
        tags: [eclipse]
    places:                          # for sun_times()
        montreal: { latitude: 45.5017, longitude: -73.5673 }
        paris: { latitude: 48.8566, longitude: 2.3522, timezone: Europe/Paris }
```

| Key | Default | |
|---|---|---|
| `timezone` | `UTC` | An IANA time zone |
| `database` | `false` | Also read the stored dates, which replace configured dates of the same key; needs DoctrineBundle |
| `dates.<key>.title` | required | |
| `dates.<key>.rule` | required | When it starts: any rule of `phil/almanac` |
| `dates.<key>.until` | none | When it ends: the first date of this rule on or after the start |
| `dates.<key>.days` | `1` | How many days, 1 to 366, without `until` |
| `dates.<key>.all_day` | `true` | `false` only for a phase, a season or an eclipse, shown at its instant |
| `dates.<key>.tags` | `[]` | Words to filter on |
| `eclipses.enabled` | `false` | Declare `solar-eclipse` and `lunar-eclipse`, at the instant of their greatest; `eclipses: true` is enough |
| `eclipses.solar_title`, `lunar_title` | `Solar eclipse`, `Lunar eclipse` | Their titles |
| `eclipses.tags` | `[eclipse]` | Their tags |
| `places.<name>.latitude` | required | Decimal degrees, −90 to 90, north positive |
| `places.<name>.longitude` | required | Decimal degrees, −180 to 180, east positive |
| `places.<name>.timezone` | `timezone` | The IANA time zone its sunrise and sunset are read in |

A date of `dates` keyed `solar-eclipse` or `lunar-eclipse` replaces the declared one: `rule: total solar eclipse`
keeps only the total ones. Keys are kept as written, dashes included.

The key is the date's: a lowercase letter or digit, then up to 99 of them, `.`, `-` or `_`. **Every date is read when
the container is built**: a rule the almanac cannot read, an `until` with `days`, a day shown at its time stop the
build with a message that names the date — `phil_almanac.dates.noel: "every blue moon" is not a rule the almanac
reads.` The rules are listed in `phil/almanac`'s `doc/rules.md`.
