<!-- phil-dev source-sha256=79926b70599622305b326850c07e6c02a8d148e3bdcb829a79f0ea2a0f07e21f -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les promesses du magasin forment un seul cas de test
abstrait, `TupleStoreTestCase`, exécuté sur le magasin en mémoire et sur le magasin DBAL au-dessus de SQLite en
mémoire. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Les promesses du magasin comprennent les partitions : deux locataires qui ont les mêmes identifiants ne se rencontrent
jamais, une portée sans partition fait échouer chaque appel, et une vérification d'un locataire ne voit jamais les
tuples d'un autre.

La suite d'intégration, sous `tests/Integration`, exécute les mêmes promesses sur de vrais serveurs, avec la collation
octet par octet, les identifiants et la partition les plus longs, une liste sur une longue liste, et la mise à niveau d'une table de
0.1. Elle lit `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, et est ignorée sans elles.

## Fuzzing et bancs d'essai

`tests/Fuzz/relation-checker.php` construit des graphes à partir des octets du fuzzeur — groupes imbriqués et cycliques,
dossiers sous eux-mêmes, tous les membres, exclusions — et établit que chaque vérification répond ou refuse avec
`TraversalBudgetExceeded`, qu'une explication existe exactement quand la vérification dit oui, et qu'une liste ne
contient que ce qui réussit la vérification, et tout quand elle se dit complète. `composer fuzz -- relation-checker`
l'exécute dans le monodépôt.

`tests/Benchmark/RelationCheckerBench.php` mesure une vérification et une liste sur des chaînes de 4 à 16 dossiers.

## Tester du code qui vérifie l'accès

Construisez le modèle de l'application avec un `InMemoryTupleStore` sur une `MockClock`, écrivez les tuples du cas avec
`RelationWriter`, puis vérifiez. Avancer l'horloge au-delà d'une expiration teste un accès qui se termine de lui-même ; une `FixedPartition`, ou une
portée que le test change, teste du code qui sert plusieurs locataires.
