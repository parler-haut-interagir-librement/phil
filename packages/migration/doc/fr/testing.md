<!-- phil-dev source-sha256=8e16be1ad7b5bb9efe99b8778dd9e226c146be31acb1504588ff3ef8ed887117 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Announcements`, `Release`, `News` | Les notes et les nouvelles par migration, l'ordre de l'exécution, les locales, une note ou une nouvelle annoncée une fois, ce qui est refusé, le vidage |
| `Channel`, `Messages` | Les notes et les nouvelles d'une locale, les en-têtes et les liens, les titres numérotés et leur place, la coupe à une espace, les longueurs en caractères, les bornes d'un canal |
| `Announcer`, `ChatterPublisher` | Chaque canal informé, un canal en échec journalisé une fois et abandonné, le transport du message de clavardage |
| `AnnouncingMigrationFactory`, `AnnouncingMigrator`, `AnnouncingMigration` | De vraies migrations Doctrine sur SQLite en mémoire : une annonce par exécution, une migration sautée et une migration simple, une exécution à blanc, une exécution échouée avec et sans tout ou rien, rien à exécuter, des notes laissées par une tentative antérieure, une migration qui n'a pas reçu les annonces |

```sh
vendor/bin/phpunit packages/migration/tests
```

## Bancs d'essai

Les sujets phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/migration/tests/Benchmark
```
