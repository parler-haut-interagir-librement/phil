<!-- phil-dev source-sha256=1e7fbfc307f50efbfc11784e72435bc4d75fd31da087e39b537ec7ed234280eb -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Url` | Chaque forme acceptée et refusée, la normalisation, les limites de longueur, `isOn()`, `display()`, `segments()` |
| `KnownUrlType` | Chaque domaine de chaque genre, les profils Mastodon et leurs imitations, les libellés |
| `UrlTypes`, `TypedUrl` | La détection parmi des ensembles dans l'ordre, les genres de l'application avant ou après les genres internationaux, les refus |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/url/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `Url::fromString()` et `display()` sur des adresses courtes,
typiques, internationales et longues, et sur une adresse refusée. Depuis la racine du monodépôt :

```sh
composer bench -- packages/url/tests/Benchmark
```
