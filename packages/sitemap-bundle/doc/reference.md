# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\SitemapBundle\PhilSitemapBundle` | `AbstractBundle` | The `phil_sitemap` configuration and the services |
| `…\Infrastructure\DependencyInjection\SitemapServices` | `final class` | Registers the services and the provider tag |
| `…\Infrastructure\Sitemaps` | `final readonly class` | Builds the sitemap: the providers, then the event |
| `…\Infrastructure\PopulateSitemap` | event | `urls`, `section` |
| `…\Infrastructure\Routing\RouteUrlProvider` | `UrlProvider` | The `sitemap` option of routes |
| `…\Infrastructure\Routing\InvalidRouteOption` | `InvalidArgumentException` | A route option that makes no page |
| `…\Infrastructure\Http\SitemapController` | controller | `index($request)` and `file($request, $name)`, with their cache headers |
| `…\Infrastructure\Console\DumpSitemapsCommand` | command | `phil:sitemap:dump` |

## Services

| Id | When |
|---|---|
| `Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider` | With `route_options`; every autoconfigured provider is tagged after it |
| `Phil\SitemapBundle\Infrastructure\Sitemaps` | Always; public |
| `Phil\SitemapBundle\Infrastructure\Http\SitemapController` | Always; public |
| `Phil\SitemapBundle\Infrastructure\Console\DumpSitemapsCommand` | Always |

## Constants

| Constant | Value |
|---|---|
| `SitemapServices::PROVIDER_TAG` | `phil_sitemap.url_provider` |
| `SitemapServices::MAX_PER_FILE` | `50000` |
| `SitemapController::INDEX_ROUTE` | `phil_sitemap_index` |
| `SitemapController::FILE_ROUTE` | `phil_sitemap_file` |

The types of pages and sections are `phil/sitemap`'s: see its reference.
