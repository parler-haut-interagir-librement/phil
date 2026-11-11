# Configuration

An example, with every option; the defaults are in the table below.

```yaml
phil_sitemap:
    default_section: main
    items_by_set: 10000
    dump_directory: '%kernel.project_dir%/public'
    cache_max_age: 86400
    route_options: true
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
| `defaults.priority` | `null` | 0.0 to 1.0, in steps of 0.1 |
| `defaults.changefreq` | `null` | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` or `never` |
| `defaults.lastmod` | `null` | `now` — the clock's time — or a date |

The defaults are what a route's option falls back on; a provider says what it knows of its own pages. A default left
`null` writes nothing. A default the pages could not take — a priority between two steps, a `lastmod` that is neither
`now` nor a date — is refused when the container is built, not when the sitemap is.
