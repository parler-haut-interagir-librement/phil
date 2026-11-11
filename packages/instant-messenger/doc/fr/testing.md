<!-- phil-dev source-sha256=0776030eb9aa745c0c2c26021c710255d6edcce1a53d515783e5947a7546c23f -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `KnownMessenger` | Chaque forme d'identifiant de chaque service, les numéros de téléphone hors de leur plan ou avec un poste refusés, leurs liens, les liens étrangers refusés, l'affichage, les liens, les noms |
| `Handle`, `Messengers` | Les identifiants canoniques, l'égalité, les refus, la détection dans l'ordre, les services d'une application en premier |

Infection impose à la bibliothèque 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/instant-messenger/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `Messengers::detect()` sur chaque service connu, pour des
entrées lues par le premier service, par un service suivant ou par aucun. À la racine du monodépôt :

```sh
composer bench -- packages/instant-messenger/tests/Benchmark
```
