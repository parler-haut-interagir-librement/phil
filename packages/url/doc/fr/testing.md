<!-- phil-dev source-sha256=b1cd8540bebb72a47a244d4545154442005a5430a9e2b26596f897d09c09f3f7 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Url` | Chaque forme acceptée et refusée, la normalisation, les limites de longueur, les traits d'union en troisième et quatrième place d'une étiquette, `isOn()`, `display()`, `segments()`, les mêmes résultats sous une locale UTF-8 |
| `HostDisplay` | Un hôte dans ses propres lettres ou en ASCII, avec le vérificateur d'usurpation d'ICU et sans lui ; un hôte ASCII simple jamais vérifié |
| `ServiceUrl`, `ServiceHosts` | Hôtes déclarés et non déclarés, noms, adresses IPv4 et IPv6, les règles gardées de `Url`, références résolues sur le même service ou refusées |
| `KnownUrlType` | Chaque domaine de chaque genre, les profils Mastodon et leurs imitations, les libellés |
| `UrlTypes`, `TypedUrl` | La détection parmi des ensembles dans l'ordre, les genres de l'application avant ou après les genres internationaux, les refus |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/url/tests
```

La suite passe aussi sur un PHP sans `ext-intl`, où `symfony/polyfill-intl-idn` convertit les hôtes : l'affichage attendu d'un
hôte international est alors sa forme ASCII, et les tests qui exigent le vérificateur d'usurpation d'ICU sont sautés.
Installez le paquet seul, hors du monodépôt dont la racine tient lieu de polyfills Symfony, et lancez PHPUnit avec un PHP
qui laisse `ext-intl` de côté.

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
