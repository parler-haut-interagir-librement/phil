# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later, with zlib | `--gzip` |
| `phil/sitemap` | ^0.1 | The pages, the sections and files, the XML |
| `symfony/routing`, `symfony/http-foundation`, `symfony/http-kernel` | ^8.2 | The route option, the served sitemap |
| `symfony/console` | ^8.2 | `phil:sitemap:dump` |
| `symfony/clock`, `psr/clock` | | `lastmod: now` |
| `psr/event-dispatcher` | ^1.0 | `PopulateSitemap` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\SitemapBundle\PhilSitemapBundle::class => ['all' => true],
];
```

## Routes

The served sitemap needs the bundle's routes; a site that only dumps its sitemap can leave them out.

```yaml
# config/routes/phil_sitemap.yaml
phil_sitemap:
    resource: '@PhilSitemapBundle/config/routes.php'
```

| Route | Path |
|---|---|
| `phil_sitemap_index` | `/sitemap.xml` |
| `phil_sitemap_file` | `/sitemap.{name}.xml` |

A site whose web server serves the dumped files from `public/` has them answer first: Symfony is not reached.
