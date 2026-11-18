<!-- phil-dev source-sha256=1aa6af1e1eff64bb1ab8b2a1a39e341f2bcfd17c7c8bd3718480fdb1d5c47f57 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `TrashItem`, `ResourceReference`, les exceptions | Chaque vérification et chaque borne, les heures en UTC, un parent avec un deux-points dans son identifiant, le mapping |
| `Trash` | La mise à la corbeille et la restauration, un parent disparu, la conservation par type, la purge dans l'ordre, les événements, sans horloge ni répartiteur |
| `PurgeTrashCommand` | Les limites, les limites refusées |
| `PhilTrashBundle` | Les services, l'autoconfiguration, la configuration, le mapping |
| `BinaryKeysListener` | Les clés en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, les options qu'un champ a déjà conservées |

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : quatre
ressources dont les identifiants ne diffèrent que par la casse ou l'accent sont chacune trouvées par leur seul
identifiant, et sur MySQL les clés sont en `utf8mb4_bin` et les tables de la version précédente y arrivent par le diff
du schéma. Le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est sauté
sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/trash-bundle/tests/Integration
```

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, une entité `Article` et son
gestionnaire : `TrashBundleTest` met à la corbeille, liste, restaure sous un autre parent, puis purge une fois qu'une
horloge avancée dépasse la durée de conservation.

```sh
vendor/bin/phpunit packages/trash-bundle/tests
```
