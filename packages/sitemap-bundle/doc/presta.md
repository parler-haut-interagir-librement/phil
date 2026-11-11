# Coming from PrestaSitemapBundle

| PrestaSitemapBundle | phil/sitemap-bundle |
|---|---|
| `presta_sitemap.defaults` | `phil_sitemap.defaults` |
| `presta_sitemap.default_section` | `phil_sitemap.default_section` |
| `presta_sitemap.items_by_set` | `phil_sitemap.items_by_set` |
| the `sitemap` route option | the same option, the same keys: `priority`, `changefreq`, `lastmod`, `section` |
| `SitemapPopulateEvent` | `PopulateSitemap`: `$event->urls` and `$event->section` |
| `$urls->addUrl($url, $section)` | `$urls->add($url, $section)` |
| `UrlConcrete($loc, $lastmod, $changefreq, $priority)` | `Url($loc, $lastmod, ChangeFrequency::…, $priority)` |
| `UrlConcrete::CHANGEFREQ_MONTHLY` | `ChangeFrequency::Monthly` |
| `GoogleMultilangUrlDecorator` | `Url(alternates: [new Alternate('fr', $loc), …])` |
| `GoogleImageUrlDecorator` | `Url(images: [new Image($loc), …])` |
| `presta:sitemaps:dump --gzip` | `phil:sitemap:dump --gzip` |
| `PrestaSitemapBundle/config/routing.yml` | `@PhilSitemapBundle/config/routes.php` |

A listener of `SitemapPopulateEvent` becomes a listener of `PopulateSitemap`, or a `UrlProvider`. A priority is in
steps of 0.1, and a `changefreq` an enum, so a typo fails instead of writing an invalid sitemap.
