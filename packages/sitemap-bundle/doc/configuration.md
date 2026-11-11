# Configuration

An example, with every option; the defaults are in the table below.

```yaml
phil_sitemap:
    default_section: main
    items_by_set: 10000
    dump_directory: '%kernel.project_dir%/public'
    cache_max_age: 86400
    route_options: true
    allow_localhost: false
    defaults:
        priority: 0.5
        changefreq: weekly
        lastmod: '2026-11-01'
```

| Option | Default | Role |
|---|---|---|
| `default_section` | `default` | The section of a route whose option names none: 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit, not ending in an underscore and digits |
| `items_by_set` | `50000` | The most pages a file holds, 1 to 50,000; a section with more is cut into numbered parts |
| `dump_directory` | `%kernel.project_dir%/public` | Where `phil:sitemap:dump` writes when no directory is given |
| `cache_max_age` | `3600` | The seconds a served sitemap may be kept by browsers and shared caches; `0` keeps it in none ([serving-and-dumping.md](serving-and-dumping.md)) |
| `route_options` | `true` | Whether the routes that carry a `sitemap` option make pages |
| `allow_localhost` | `false` | Whether pages may be on `localhost` or under `.localhost`: `true` in development and tests only |
| `defaults.priority` | `null` | 0.0 to 1.0, in steps of 0.1 |
| `defaults.changefreq` | `null` | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` or `never` |
| `defaults.lastmod` | `null` | `now` — the clock's time — or a date |

The defaults are what a route's option falls back on; a provider says what it knows of its own pages. A default left
`null` writes nothing. A default the pages could not take — a priority between two steps, a `lastmod` that is neither
`now` nor a date — is refused when the container is built, not when the sitemap is.


## Localhost in development and tests

`localhost` and the names under `.localhost` are refused by default, as `phil/sitemap` refuses them: no search engine
reaches them. A development machine and the tests allow them, so that `/sitemap.xml` and a dump with
`--base-url http://localhost:8000` work there:

```yaml
when@dev:
    phil_sitemap:
        allow_localhost: true

when@test:
    phil_sitemap:
        allow_localhost: true
```

The route pages, the served index and the dumped index read it. A provider of the application reads the container
parameter `phil_sitemap.allow_localhost` and passes it to the pages it makes: `new Url($loc, allowLocalhost:
$allowLocalhost)`, and the same to `Alternate` and `Image`. An IP address, `http://127.0.0.1:8000/`, stays refused.
