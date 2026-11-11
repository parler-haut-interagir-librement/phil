<!-- phil-dev source-sha256=45a10f7fdf432d415b86a2c7f91fc5f46a04d1571fe230bb6bcc92caf7ad7e1d -->
# Points d'extension

Ce qu'une application implémente, écoute, configure ou utilise. Les types de pages et de sections sont ceux de
`phil/sitemap`. Voyez [Pages](pages.md).

## Fournisseurs

Un service qui implémente `Phil\Sitemap\UrlProvider` reçoit par autoconfiguration l'étiquette
`phil_sitemap.url_provider`, avec `after: RouteUrlProvider::class`, pour que les pages des routes viennent en premier.
Un bundle qui étiquette ses fournisseurs à la main nomme `RouteUrlProvider::class` dans le `after` de l'étiquette pour
garder cet ordre. Quand on lui demande une section, un fournisseur peut retourner plus tôt.

## Événements

| Événement | Quand | Ce que fait un écouteur |
|---|---|---|
| `Phil\SitemapBundle\Infrastructure\PopulateSitemap` | Après les routes et les fournisseurs, chaque fois qu'un plan de site est construit | Ajoute des pages à `$event->urls` ; `$event->section` est la section demandée, ou `null` pour toutes |

## Options de route

L'option `sitemap` d'une route — `true`, ou `priority`, `changefreq`, `lastmod` et `section` — produit sa page sans
une ligne de PHP ; `route_options: false` les désactive.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `default_section` | La section d'une route qui n'en nomme aucune |
| `items_by_set` | Le nombre maximal de pages d'un fichier |
| `dump_directory` | Là où écrit `phil:sitemap:dump` |
| `cache_max_age` | Combien de temps un plan de site servi peut rester en cache |
| `defaults` | La priorité, la fréquence de changement et la dernière modification d'une route qui n'en donne aucune |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\SitemapBundle\Infrastructure\Sitemaps` | Injecté pour construire un plan de site dans le code ; public |
| Les routes `phil_sitemap_index` et `phil_sitemap_file` | Importées là où l'application veut que `/sitemap.xml` soit servi — voyez [Installation](installation.md) |
