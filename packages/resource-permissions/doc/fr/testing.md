<!-- phil-dev source-sha256=d15c0343fb5272a1bb29408fe1be44ed4fac0a0bcfa90e5d6f25c1d921fae68b -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les promesses du magasin forment un seul cas de test
abstrait, `TupleStoreTestCase`, exécuté sur le magasin en mémoire et sur le magasin DBAL au-dessus de SQLite en
mémoire. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

La suite d'intégration, sous `tests/Integration`, exécute les mêmes promesses sur de vrais serveurs, avec la collation
octet par octet, les identifiants les plus longs et une liste sur une longue liste. Elle lit `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`, et est ignorée sans elles.

## Fuzzing et bancs d'essai

`tests/Fuzz/relation-checker.php` construit des graphes à partir des octets du fuzzeur — groupes imbriqués et cycliques,
dossiers sous eux-mêmes, tous les membres, exclusions — et établit que chaque vérification répond ou refuse avec
`TraversalBudgetExceeded`, qu'une explication existe exactement quand la vérification dit oui, et qu'une liste ne
contient que ce qui réussit la vérification, et tout quand elle se dit complète. `composer fuzz -- relation-checker`
l'exécute dans le monodépôt.

`tests/Benchmark/RelationCheckerBench.php` mesure une vérification et une liste sur des chaînes de 4 à 16 dossiers.

## Tester du code qui vérifie l'accès

Construisez le modèle de l'application avec un `InMemoryTupleStore` sur une `MockClock`, écrivez les tuples du cas avec
`RelationWriter`, puis vérifiez. Avancer l'horloge au-delà d'une expiration teste un accès qui se termine de lui-même.
