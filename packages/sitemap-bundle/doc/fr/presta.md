<!-- phil-dev source-sha256=a5e04dfad47e3066fb734470048ae27415db1ebb3ddec1b7d36f9d8fb50cd5f1 -->
# Venir de PrestaSitemapBundle

| PrestaSitemapBundle | phil/sitemap-bundle |
|---|---|
| `presta_sitemap.defaults` | `phil_sitemap.defaults` |
| `presta_sitemap.default_section` | `phil_sitemap.default_section` |
| `presta_sitemap.items_by_set` | `phil_sitemap.items_by_set` |
| l'option de route `sitemap` | la même option, les mêmes clés : `priority`, `changefreq`, `lastmod`, `section` |
| `SitemapPopulateEvent` | `PopulateSitemap` : `$event->urls` et `$event->section` |
| `$urls->addUrl($url, $section)` | `$urls->add($url, $section)` |
| `UrlConcrete($loc, $lastmod, $changefreq, $priority)` | `Url($loc, $lastmod, ChangeFrequency::…, $priority)` |
| `UrlConcrete::CHANGEFREQ_MONTHLY` | `ChangeFrequency::Monthly` |
| `GoogleMultilangUrlDecorator` | `Url(alternates: [new Alternate('fr', $loc), …])` |
| `GoogleImageUrlDecorator` | `Url(images: [new Image($loc), …])` |
| `presta:sitemaps:dump --gzip` | `phil:sitemap:dump --gzip` |
| `PrestaSitemapBundle/config/routing.yml` | `@PhilSitemapBundle/config/routes.php` |

Un écouteur de `SitemapPopulateEvent` devient un écouteur de `PopulateSitemap`, ou un `UrlProvider`. Une priorité va
par pas de 0,1, et un `changefreq` est une enum : une faute de frappe échoue au lieu d'écrire un plan de site
invalide.
