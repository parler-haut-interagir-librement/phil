<!-- phil-dev source-sha256=9ff02fe9231b72bb1f28b4d2a91ed3b2838ea1df77a705141e154dfcc94e7d95 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Les entités, `ResourceReference` | Chaque vérification et chaque borne ; les chemins, la profondeur, les déplacements et les cycles ; l'association des entités |
| `Taxonomy` avec `DoctrineTaxonomyRepository` | Sur SQLite en mémoire : étiquettes trouvées, attribuées, renommées, fusionnées ; catégories créées, traduites, déplacées, fusionnées avec une seule lecture de l'arbre et un seul *flush* ; noms lus dans leurs seules langues ; branches ; tout ce qui est conservé |
| `TagsType`, `CategoryType` | Texte et listes ; l'arbre en retrait dans une langue, ses traductions lues une fois ; choix donnés ; choix multiples ; l'option de locale |
| `PhilTaxonomyBundle` | Les services et l'association des entités |
| `BinaryKeysListener` | Les clés en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, les options qu'un champ a déjà conservées |

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : quatre slugs
qui ne diffèrent que par la casse ou l'accent sont quatre étiquettes, chacune trouvée par son seul slug, et sur MySQL
les clés sont en `utf8mb4_bin` et les tables de la version précédente y arrivent par le diff du schéma. Le test de
chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est sauté sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/taxonomy-bundle/tests/Integration
```

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite et le bundle : elle étiquette
et classe une ressource, choisit une catégorie en `fr_CA`, et lit l'aide en français.

```sh
vendor/bin/phpunit packages/taxonomy-bundle/tests
```

## Bancs d'essai

Les sujets phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/taxonomy-bundle/tests/Benchmark
```
