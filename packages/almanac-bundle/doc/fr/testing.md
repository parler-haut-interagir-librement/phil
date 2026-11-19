<!-- phil-dev source-sha256=adbc2df7a1859666da86965a018359acf407405a7a840e0d69c7a80f220ac3bf -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `StoredDate`, `Definitions`, `ConfiguredDates`, `AlmanacFactory` | Une définition conservée, rendue et modifiée ; les dates stockées qui remplacent les dates configurées ; l'almanach qui voit une date aussitôt, dans son fuseau horaire |
| `DateDraft` | Un brouillon à partir d'une définition, et retour ; chaque refus, sur son champ, et la date entière vérifiée seulement une fois ses champs valides |
| `TagsTransformer`, `AlmanacExtension`, `SkyExtension`, `Places` | Étiquettes lues et écrites ; les dates d'une période dans Twig ; le jour solaire d'un lieu par son nom ou comme `Place`, un texte lu dans son fuseau horaire ; les éclipses d'une période, d'un astre ou des deux, et un astre inconnu refusé ; un lieu inconnu nommé avec ceux qui existent |
| `BinaryKeysListener` | `date_key` en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, où des clés qui ne diffèrent que par la casse ou l'accent sont quatre lignes, les options qu'un champ a déjà conservées, une autre entité laissée telle quelle |
| `PhilAlmanacBundle` | Les services avec et sans base de données, les dates configurées construites en définitions, les éclipses déclarées et une date qui en remplace une, les lieux et leur fuseau horaire par défaut, Doctrine trouvé parmi les bundles d'un noyau, chaque configuration refusée |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle : des
dates stockées enregistrées, lues, qui remplacent une date configurée, puis supprimées ; le rendu de `almanac_dates`, éclipses déclarées comprises ;
le rendu de `sun_times` et de `almanac_eclipses` ; le formulaire soumis, refusé en français, et son rendu. Lorsque le bundle est installé seul, FrameworkBundle n'étant
qu'un prérequis de développement, elle est sautée.

```sh
vendor/bin/phpunit packages/almanac-bundle/tests
```

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : les clés
`harvest`, `Harvest`, `HARVEST` et `harvést` sont quatre dates sous la clé unique, chacune trouvée par sa seule clé, la
même clé écrite deux fois est toujours refusée, et sur MySQL `date_key` est en `utf8mb4_bin` et la table de la version
précédente l'atteint par le diff du schéma. Le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/almanac-bundle/tests/Integration
```
