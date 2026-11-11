# Extension points

What an application implements, listens to, configures or relies on. The types of pages and sections are
`phil/sitemap`'s. See [Pages](pages.md).

## Providers

A service implementing `Phil\Sitemap\UrlProvider` is tagged `phil_sitemap.url_provider` by autoconfiguration, with
`after: RouteUrlProvider::class`, so that the pages of the routes come first. A bundle that tags its providers by hand
names `RouteUrlProvider::class` in the tag's `after` to keep that order. Asked for one section, a provider may return
early.

## Events

| Event | When | What a listener does |
|---|---|---|
| `Phil\SitemapBundle\Infrastructure\PopulateSitemap` | After the routes and the providers, each time a sitemap is built | Adds pages to `$event->urls`; `$event->section` is the section asked for, or `null` for all |

## Route options

The `sitemap` option of a route — `true`, or `priority`, `changefreq`, `lastmod` and `section` — makes its page
without a line of PHP; `route_options: false` turns them off.

## Configuration

| Option | What it extends |
|---|---|
| `default_section` | The section of a route that names none |
| `items_by_set` | The most pages a file holds |
| `dump_directory` | Where `phil:sitemap:dump` writes |
| `cache_max_age` | How long a served sitemap may be cached |
| `defaults` | The priority, change frequency and last modification of a route that gives none |

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\SitemapBundle\Infrastructure\Sitemaps` | Injected to build a sitemap in code; public |
| The routes `phil_sitemap_index` and `phil_sitemap_file` | Imported where the application wants `/sitemap.xml` served — see [Installation](installation.md) |
