<!-- phil-dev source-sha256=5ab097efc5f1c75e9519f7e659453d77efe3de083cd20da92f6d4868787e93df -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qui est couvert |
|---|---|
| `tests/Unit` | La configuration et ses services ; un contrat des cinq stockages, tenu par les adaptateurs en mémoire de `phil/stockroom` et par ceux du bundle sur SQLite en mémoire ; les lignes relues ; les tables, leurs collations et l'écouteur de schéma ; la commande ; les transactions, leur instruction de verrou, l'ordre des verrous, l'annulation et la jonction avec la transaction de l'appelant. |
| `tests/Functional` | Une application avec FrameworkBundle et DoctrineBundle sur SQLite : les tables dans le schéma que génère l'ORM, la réserve et ses déploiements dans une transaction, la commande. |
| `tests/Integration` | Le même contrat des stockages sur MySQL 8.4 et PostgreSQL 17, les collations octet par octet, et un article verrouillé par une transaction vu depuis une seconde connexion. |

La suite unitaire s'exécute sans noyau, sans serveur et sans réseau. Dans le monodépôt, Infection maintient le paquet à
100 % des mutants couverts.

Les tests d'intégration lisent l'URL de leur serveur dans une variable d'environnement et sont ignorés sans elle :
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`. Ils créent leurs tables sous le préfixe `phil_stockroom_test_`, en
les supprimant d'abord.

## Tester une application sur la réserve

Configurez le stockage `memory` dans l'environnement de test pour des tests rapides qui n'ont pas besoin des tables : les
adaptateurs en mémoire respectent le même contrat et sont vidés entre les requêtes d'un worker. Un test des
transactions propres à l'application avec la réserve garde `dbal` sur SQLite, avec les tables créées par
`phil:stockroom:schema --force` ou par l'outil de schéma de l'ORM, que nourrit l'écouteur du bundle.
