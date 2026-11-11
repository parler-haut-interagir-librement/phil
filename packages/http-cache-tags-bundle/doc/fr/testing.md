<!-- phil-dev source-sha256=615c8ef56fa2df763e2ece8ecc9eaa3b8078714271c278fd5fbea6df13082f64 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `CacheTag`, `ResponseTags`, `Invalidator` | Chaque vérification ; une seule fois chacune ; une seule purge ; aucun purgeur |
| `ResponseTagsListener`, `#[CacheTag]`, `cache_tag()`, `InvalidationFlushListener` | Les paramètres de route, ceux qui manquent, l'en-tête, les sous-requêtes, la fin d'une requête |
| `VarnishPurger`, `CloudflarePurger`, `InvalidateOnFlushListener` | Les requêtes envoyées, les lots de cent, les bornes des statuts ; les entités d'un flush |
| `PhilHttpCacheTagsBundle` | Les services, les en-têtes, les purgeurs, les réglages refusés |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle qui
purge un Varnish qui note ce qu'on lui demande, avec des sessions et un écouteur qui étiquette tard :
`HttpCacheTagsBundleTest` produit le rendu d'une page étiquetée, étiquette tardive comprise, et la purge quand un flush
la renomme, une fois la requête terminée.

```sh
vendor/bin/phpunit packages/http-cache-tags-bundle/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/http-cache-tags-bundle/tests/Benchmark
```
