<!-- phil-dev source-sha256=de45b057275e52148bb6d762e00beadef1fa9cfadacd079b0dc09a0350f465dd -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\SitemapBundle\PhilSitemapBundle` | `AbstractBundle` | La configuration `phil_sitemap` et les services |
| `…\Infrastructure\DependencyInjection\SitemapServices` | `final class` | Enregistre les services et l'étiquette des fournisseurs |
| `…\Infrastructure\Sitemaps` | `final readonly class` | Construit le plan de site : les fournisseurs, puis l'événement |
| `…\Infrastructure\PopulateSitemap` | événement | `urls`, `section` |
| `…\Infrastructure\Routing\RouteUrlProvider` | `UrlProvider` | L'option `sitemap` des routes |
| `…\Infrastructure\Routing\InvalidRouteOption` | `InvalidArgumentException` | Une option de route qui ne produit aucune page |
| `…\Infrastructure\Http\SitemapController` | contrôleur | `index($request)` et `file($request, $name)`, avec leurs en-têtes de cache |
| `…\Infrastructure\Console\DumpSitemapsCommand` | commande | `phil:sitemap:dump` |

## Services

| Identifiant | Quand |
|---|---|
| `Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider` | Avec `route_options` ; chaque fournisseur autoconfiguré est étiqueté après lui |
| `Phil\SitemapBundle\Infrastructure\Sitemaps` | Toujours ; public |
| `Phil\SitemapBundle\Infrastructure\Http\SitemapController` | Toujours ; public |
| `Phil\SitemapBundle\Infrastructure\Console\DumpSitemapsCommand` | Toujours |

## Constantes

| Constante | Valeur |
|---|---|
| `SitemapServices::PROVIDER_TAG` | `phil_sitemap.url_provider` |
| `SitemapServices::MAX_PER_FILE` | `50000` |
| `SitemapController::INDEX_ROUTE` | `phil_sitemap_index` |
| `SitemapController::FILE_ROUTE` | `phil_sitemap_file` |

Les types de pages et de sections sont ceux de `phil/sitemap` : voyez sa référence.
