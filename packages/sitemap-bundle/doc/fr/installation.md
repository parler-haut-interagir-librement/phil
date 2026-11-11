<!-- phil-dev source-sha256=a6a5d209e6b4638ad1382311cfb1465fd5ae6e0778a0e886c704a4df03a7fd49 -->
# Installation

## Prérequis

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent, avec zlib | `--gzip` |
| `phil/sitemap` | ^0.1 | Les pages, les sections et les fichiers, le XML |
| `symfony/routing`, `symfony/http-foundation`, `symfony/http-kernel` | ^8.2 | L'option de route, le plan de site servi |
| `symfony/console` | ^8.2 | `phil:sitemap:dump` |
| `symfony/clock`, `psr/clock` | | `lastmod: now` |
| `psr/event-dispatcher` | ^1.0 | `PopulateSitemap` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Phil\SitemapBundle\PhilSitemapBundle::class => ['all' => true],
];
```

## Routes

Le plan de site servi a besoin des routes du bundle ; un site qui ne fait qu'écrire son plan de site en fichiers peut
les omettre.

```yaml
# config/routes/phil_sitemap.yaml
phil_sitemap:
    resource: '@PhilSitemapBundle/config/routes.php'
```

| Route | Chemin |
|---|---|
| `phil_sitemap_index` | `/sitemap.xml` |
| `phil_sitemap_file` | `/sitemap.{name}.xml` |

Pour un site dont le serveur Web sert les fichiers écrits depuis `public/`, ce sont eux qui répondent en premier :
Symfony n'est pas atteint.
