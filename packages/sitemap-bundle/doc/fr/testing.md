<!-- phil-dev source-sha256=52f4ae5f9a9ce9b326ccb3c28c370feeea1ed2e4338ce504b166a49606ef1fc7 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilSitemapBundle`, `SitemapServices` | Les valeurs par défaut, chaque option, les services et leurs étiquettes, l'autoconfiguration, ce que la configuration refuse |
| `RouteUrlProvider`, `Sitemaps`, `PopulateSitemap` | `true` et chaque clé, les valeurs par défaut, `now` d'après l'horloge, une date dans le fuseau horaire de l'horloge, les routes localisées et leurs variantes, une seule section, l'événement, chaque mauvaise option, une route qui a besoin de paramètres ; les pages des routes avant celles d'un fournisseur qui a sa propre priorité |
| Une application complète | FrameworkBundle, des routes avec l'option, un fournisseur et un écouteur : l'index, une section, une section localisée, des parties numérotées, des images, des `404`, et l'écriture avec `--gzip`, `--base-url` et l'URL de base du routeur, les anciens fichiers supprimés |

```sh
vendor/bin/phpunit packages/sitemap-bundle/tests
```

## Bancs d'essai

`RouteUrlProviderBench` construit les pages de 60 à 3 000 routes, avec un routeur rechargé et avec un routeur déjà
chargé. Depuis la racine du monodépôt :

```sh
composer bench -- packages/sitemap-bundle/tests/Benchmark
```
