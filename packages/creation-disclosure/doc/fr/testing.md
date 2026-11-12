<!-- phil-dev source-sha256=56881b7c9ac39e537939d3f8b7f8311e0558f37a82984757ef522e4ceca9b787 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, tourne sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection tient le paquet à 100 % des mutants couverts.

La cible de fuzzing `tests/Fuzz/stored-disclosure.php` nourrit `CreationDisclosure::fromArray()` de ce que personne
n'a écrit : elle lit une déclaration qui se réécrit à l'identique, ou lance `Exception\InvalidDisclosure`, et rien
d'autre.

## Tester une application

Les types sont des valeurs : un test construit la déclaration dont il a besoin avec `CreationDisclosure::of()` et
`declare()`, ou la lit du tableau que garde un jeu de données, et compare les vues avec `assertSame()`.
