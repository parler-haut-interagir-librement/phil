<!-- phil-dev source-sha256=1cac24a3b6d2052d09edcc5df3582288fc1f2f388e6584d693902aaa529f7441 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/stockroom-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6), and of each development requirement
  (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4).

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `PhilStockroomBundle` et sa configuration `phil_stockroom` : le stockage, `dbal` ou `memory`, la connexion, le préfixe
  des tables, vérifiés à la construction du conteneur, et l'horloge.
- `StockroomTables` : les six tables sous un même préfixe, les identifiants comparés octet par octet (`ascii_bin` sur
  MySQL et MariaDB, `C` sur PostgreSQL), et `configureSchema()`.
- `DbalContainers`, `DbalItems`, `DbalBalances`, `DbalJournal` et `DbalLoadLists` : les cinq ports de `phil/stockroom`
  sur une connexion Doctrine DBAL, tenus au contrat des adaptateurs en mémoire de la bibliothèque ; le journal écrit une
  fois et ordonné par une position auto-incrémentée, les moments à la microseconde en UTC ; chaque rangée lue vérifiée
  de nouveau par la bibliothèque.
- `StockroomTransactions` : les appels de la réserve en une transaction, les rangées de leurs articles verrouillées dans
  l'ordre, qui rejoignent une transaction de l'appelant sur la même connexion ; le paramètre `phil_stockroom.connection`
  pour les paquets qui la partagent.
- `StockroomSchemaListener`, qui ajoute les tables au schéma que génère l'ORM, et `phil:stockroom:schema`, qui montre ou
  crée les tables manquantes.
- `Stockroom`, `Deployments`, `LoadListReader` et `StockReader` comme services sur les stockages configurés.
- Des tests sur SQLite, MySQL 8.4 et PostgreSQL 17, une application de test, et la recette Flex dans `recipe/0.1`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
