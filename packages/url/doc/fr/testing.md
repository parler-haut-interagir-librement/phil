<!-- phil-dev source-sha256=50bb5b1e4ffb2d336945445246c4dfd06a4b601a9ecc9a80fdccdf3865bbff4d -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Url` | Chaque forme acceptée et refusée, la normalisation, les limites de longueur, `isOn()`, `display()`, `segments()`, les mêmes résultats sous une locale UTF-8 |
| `ServiceUrl`, `ServiceHosts` | Hôtes déclarés et non déclarés, noms, adresses IPv4 et IPv6, les règles gardées de `Url`, références résolues sur le même service ou refusées |
| `KnownUrlType` | Chaque domaine de chaque genre, les profils Mastodon et leurs imitations, les libellés |
| `UrlTypes`, `TypedUrl` | La détection parmi des ensembles dans l'ordre, les genres de l'application avant ou après les genres internationaux, les refus |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/url/tests
```

## Fuzzing

La cible de fuzzing `url-service-addresses`, dans `tests/Fuzz/`, donne à `Url::fromString()`, à `ServiceHosts`, à
`ServiceUrl::fromString()` et à `resolve()` des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/url-service-addresses` : chacun donne une adresse qui se relit comme elle-même, sur un hôte déclaré et
sur le même service, ou lance `InvalidUrl`, jamais une autre exception. Depuis la racine du monodépôt :

```sh
composer fuzz -- url-service-addresses
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `Url::fromString()` et `display()` sur des adresses courtes,
typiques, internationales et longues, et sur une adresse refusée. Depuis la racine du monodépôt :

```sh
composer bench -- packages/url/tests/Benchmark
```
