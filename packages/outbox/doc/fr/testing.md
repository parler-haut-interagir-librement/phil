<!-- phil-dev source-sha256=97701a814048170ff1c05dd7f1241be9ea3353433110c397a492986427f73fd5 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données ni réseau : l'entrepôt en mémoire
réussit les tests de contrat de `src/Test`, et le relais s'exécute sur une horloge figée de `symfony/clock`. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Dans les tests d'une application

`InMemoryOutboxStore` et une horloge figée donnent un relais qu'un test pilote lot par lot :

```php
$store = new InMemoryOutboxStore();
$clock = new MockClock('2026-11-17 10:00:00');
$relay = new Relay($store, $codecs, $dispatcher, $clock);

$service->complete($holder, $set);
$relay->relay();

$clock->sleep(60); // past the first delay of a failed attempt
$relay->relay();
```

## La cible de fuzzing

`tests/Fuzz/payload.php` donne au lecteur des charges utiles stockées des octets que personne n'a écrits : chacun est
lu comme une charge utile dans ses bornes ou refusé comme telle, et une charge utile lue est réécrite en la même charge
utile. `composer fuzz` l'exécute à partir des germes de `tests/Fuzz/corpus/payload`.

## Le banc d'essai

`tests/Benchmark/RelayBench.php` mesure l'enregistrement d'une centaine d'événements, puis leur relais en un seul lot
sur l'entrepôt en mémoire : `composer bench`.
