# phil/sitemap-bundle

**`/sitemap.xml` for a Symfony site, from route options, URL providers and an event.**

phil/sitemap-bundle brings phil/sitemap into a Symfony application. Three sources make the pages: the routes that
carry a `sitemap` option, every service that implements `UrlProvider`, and the listeners of the `PopulateSitemap`
event. A localized route gives one page per locale, each with the others as alternates. The bundle serves the index
at `/sitemap.xml` and each file at `/sitemap.{name}.xml`, or writes them as files with `phil:sitemap:dump`, gzipped
with `--gzip` and under another base URL with `--base-url`. A bad route option fails at once, as an
`InvalidRouteOption` naming the route.

Use it in a Symfony site that wants its fixed routes and its database pages in one sitemap. A small site serves it on
request. A large one dumps it once a release is deployed, since building the index asks every provider for every
page. An application coming from PrestaSitemapBundle keeps the same route option, and finds its configuration keys
under `phil_sitemap`. Outside Symfony, phil/sitemap writes the XML on its own.

It plugs into the Symfony router, the event dispatcher, the console and the clock. Providers are autoconfigured, so a
class that implements `UrlProvider` is enough. The bundle gathers the pages, and the library checks and writes them.

## Status

| Part | State |
|---|---|
| The `sitemap` route option, with defaults and a default section, localized routes as alternates | Implemented and tested |
| Providers, autoconfigured, and the `PopulateSitemap` event | Implemented and tested |
| `/sitemap.xml` and `/sitemap.{name}.xml` | Implemented and tested |
| `phil:sitemap:dump`, with `--gzip` and `--base-url` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the routes. |
| [Configuration](doc/configuration.md) | Sections, defaults, files, the dump directory. |
| [Pages](doc/pages.md) | The route option, providers and the populate event. |
| [Serving and dumping](doc/serving-and-dumping.md) | The served sitemap and the files the dump writes. |
| [Coming from PrestaSitemapBundle](doc/presta.md) | What each part of that bundle becomes. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
# config/routes/phil_sitemap.yaml
phil_sitemap:
    resource: '@PhilSitemapBundle/config/routes.php'
```

```php
#[Route('/contact', name: 'contact', options: ['sitemap' => ['priority' => 0.8, 'changefreq' => 'never', 'section' => 'pages']])]
```

```bash
bin/console phil:sitemap:dump --gzip
```

## Requirements

PHP 8.5 or later with zlib, Symfony 8.2 or later, and `phil/sitemap`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
