# Changelog

All notable changes to `phil/sitemap-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-11

### Added

- The option `allow_localhost`, `false` by default, also the container parameter `phil_sitemap.allow_localhost`:
  `true`, in development and tests, lets the route pages, the served index and `phil:sitemap:dump` be on `localhost`
  or under `.localhost`. `RouteUrlProvider`, `SitemapController` and `DumpSitemapsCommand` take it as their last
  argument.

### Changed

- The package requires `phil/sitemap` 0.1.1, which reads the addresses by `phil/url`: a page on `localhost`, under
  `.localhost` or on an IP address is refused unless `allow_localhost` allows the first two.

## 0.1.0-alpha - 2026-11-11

### Added

- `RouteUrlProvider`: the `sitemap` option of routes — `true`, or `priority`, `changefreq`, `lastmod` and `section`
  —, localized routes as `hreflang` alternates; `InvalidRouteOption`.
- `Sitemaps`: every `UrlProvider`, autoconfigured after `RouteUrlProvider`, then the `PopulateSitemap` event.
- `PhilSitemapBundle` and the `phil_sitemap` configuration: `default_section`, `items_by_set`, `dump_directory`,
  `route_options`, `cache_max_age` and `defaults` (`priority`, `changefreq`, `lastmod`). `defaults.priority` takes
  steps of 0.1, `defaults.lastmod` "now" or a date, and `default_section` a valid section: a value the pages could
  not take is refused when the container is built.
- `SitemapController`: `/sitemap.xml` and `/sitemap.{name}.xml`, in `config/routes.php`; `/sitemap.{name}.xml`
  builds the one section the file belongs to, read by `Sitemap::sectionOfFile()`. The served sitemap is `public`
  with a `max-age` of `cache_max_age` (3600 by default), carries the latest `lastmod` of its pages as
  `Last-Modified` and an `ETag`, and answers `304` to a request that holds it.
- `phil:sitemap:dump`, with `--gzip` and `--base-url`. It builds the sitemap before touching a file, writes each file
  under a temporary name and renames it into place, the index last, then removes the files of the earlier dump it
  does not have: a provider that fails leaves the previous dump whole, and a write that fails is an error that keeps
  the previous index. It refuses to write an index under `http://localhost`, the router's address on the command
  line when `framework.router.default_uri` is not set and no `--base-url` is given.
- The documentation in `doc/`, indexed by the README, whose configuration example is labelled as one, apart from
  the defaults.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
