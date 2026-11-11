# Testing

| Area | What is covered |
|---|---|
| `PhilSitemapBundle`, `SitemapServices` | The defaults, every option, the services and their tags, autoconfiguration, what the configuration refuses |
| `RouteUrlProvider`, `Sitemaps`, `PopulateSitemap` | `true` and every key, the defaults, `now` from the clock, a date in the clock's time zone, localized routes and their alternates, one section, the event, every bad option, a route that needs parameters; the pages of the routes before those of a provider with a priority of its own |
| A whole application | FrameworkBundle, routes with the option, a provider and a listener: the index, a section, a localized section, numbered parts, images, `404`s, and the dump with `--gzip`, `--base-url` and the router's base URL, earlier files removed |

```sh
vendor/bin/phpunit packages/sitemap-bundle/tests
```

## Benchmarks

`RouteUrlProviderBench` builds the pages of 60 to 3,000 routes, with a router loaded again and with one already loaded. From the monorepo root:

```sh
composer bench -- packages/sitemap-bundle/tests/Benchmark
```
