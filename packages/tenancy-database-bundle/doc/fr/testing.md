<!-- phil-dev source-sha256=7246d4d3e013f5edc90edf34621d4fb7543120d7046b176b5a2b858dfc807756 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : le routeur, l'aiguillage,
le plan de copie et les stockages `dbal` sur un SQLite en mémoire, les instructions de MySQL enregistrées par une
connexion qui ne s'ouvre jamais, l'exécuteur de migrations avec un petit script PHP tenant lieu de console, arrêté pendant qu'il tourne. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/sealed-credentials.php` ouvre avec `CredentialSeal` des valeurs que personne n'a écrites, telles quelles et
scellées comme du texte brut, et vérifie que chacune s'ouvre comme des identifiants ou est refusée, jamais avec une
autre erreur ni un avertissement :

```sh
vendor/bin/phil-dev fuzz sealed-credentials --budget=60
```

## Les tests d'intégration

`tests/Integration/MigrationStopTest.php` n'exige aucun serveur : il lance la commande de migration dans une console à
part avec des migrations lentes, lui envoie SIGTERM ou SIGINT, ou la termine par une erreur fatale, et vérifie
qu'aucun processus de migration ne reste en cours. Il exige l'extension `pcntl`.

`tests/Integration` démarre une petite application — FrameworkBundle, DoctrineBundle, `phil/tenancy-bundle` et ce
bundle, trois hôtes et deux locataires dédiés — sur un vrai serveur, une fois sur MySQL et une fois sur PostgreSQL.
Chaque test supprime et crée ses bases, si bien que le compte doit avoir le droit de les créer ; le test est ignoré
sinon, et sans URL.

| Serveur | Variable lue en premier | Ensuite |
|---|---|---|
| MySQL | `PHIL_TENANCY_DATABASE_TEST_MYSQL_URL` | `PHIL_TEST_MYSQL_URL` |
| PostgreSQL | `PHIL_TENANCY_DATABASE_TEST_POSTGRESQL_URL` | `PHIL_TEST_POSTGRESQL_URL` |

```sh
vendor/bin/phil-dev services up
PHIL_TENANCY_DATABASE_TEST_MYSQL_URL='mysql://root:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit tests/Integration
```

Le compte MySQL `phil` de `phil-dev services` ne peut utiliser que `phil_test` et ne peut pas créer de bases : les tests
MySQL sont alors ignorés, chacun avec sa raison. Donnez le compte root, dont le mot de passe est `phil` dans ces
services, dans `PHIL_TENANCY_DATABASE_TEST_MYSQL_URL`, comme ci-dessus ; seuls les tests de ce bundle lisent la
variable, de sorte que les autres paquets gardent le compte restreint. Le compte PostgreSQL est celui du serveur
lui-même, qui peut créer des bases.

Chaque serveur exécute deux suites. `MySqlTenantDatabasesTest` et `PostgreSqlTenantDatabasesTest` gardent les
emplacements en mémoire ; ils couvrent deux locataires de chaque sorte lus l'un après l'autre dans une requête, sans
qu'aucune ligne de l'un soit vue par l'autre — par le gestionnaire d'entités, le SQL natif et `find()` sur la même
clé —, le refus dans une transaction, la réinitialisation d'un *worker*, deux locataires qui se relaient dans un *worker*, la création d'une base, une
migration qui échoue pour une base pendant que les autres réussissent et sont servies, et des déplacements qui
réussissent, entrent en collision sur une clé ou perdent leur stockage d'emplacements, chacun annulé jusqu'à la
source.
`MySqlStoredPlacementsTest` et `PostgreSqlStoredPlacementsTest` gardent les emplacements et les suspensions dans les
tables de la base partagée : les tables créées avec le schéma, les emplacements lus dans la base partagée pendant qu'un
locataire dédié est entré, un déplacement enregistré dans la table puis annulé, et des suspensions qui survivent à un
cache vidé et à la fin de l'unité de travail, qui ferme la connexion du stockage.

`MySqlPlacementKeysTest` et `PostgreSqlPlacementKeysTest` ne créent aucune base de données : dans la base de données
d'essai de `PHIL_TEST_MYSQL_URL` et de `PHIL_TEST_POSTGRESQL_URL`, ils placent les locataires `Rose`, `rose`, `ROSE` et
`rosé` dans quatre bases de données et relisent chaque placement. Une ligne se retrouve par le SHA-256 de la référence
du locataire, pour que la collation par défaut de MySQL, qui ignore la casse et les accents, ne fasse jamais de deux
locataires un seul.

`testTwoTenantsTakeTurnsInOneWorker` sert des requêtes avec un seul noyau, comme le fait un *worker* FrankenPHP —
chaque requête est traitée puis terminée, et les services sont réinitialisés quand la suivante commence — pour le
locataire partagé, le dédié, le partagé de nouveau, puis une requête de chacun qui lance une exception au milieu d'une
transaction, et une de chacun ensuite. Chaque requête trouve la base de son locataire sélectionnée, une table
d'identité vide, aucune transaction ouverte, la première facture de son propre locataire sur une clé que les deux
locataires utilisent, et seulement les éléments de cache de son propre locataire ; le gestionnaire d'entités est vidé
quand le locataire est entré et quand il est quitté à `kernel.terminate` ; les écritures des requêtes qui ont lancé
une exception sont annulées.

## Tester une application

- **Isolement** : semez deux locataires, un partagé et un dédié, avec des lignes sur les mêmes clés ; entrez dans l'un,
  puis dans l'autre, dans un seul test, et lisez par le dépôt, le SQL natif et `find()`.
- **Workers** : servez plusieurs requêtes avec un seul noyau, en appelant `handle()` puis `terminate()` pour chacune
  sans jamais le redémarrer, pour des locataires de bases différentes tour à tour, y compris une requête qui échoue
  dans une transaction.
- **Déplacements** : lancez `phil:tenancy:database:move` sur une copie des données de production avant un vrai
  déplacement ; la commande vérifie chaque table, et annule à la moindre différence.
- **Migrations** : lancez `phil:tenancy:database:migrate --concurrency=1` dans le pipeline, sur des bases créées par
  `phil:tenancy:database:create`.
