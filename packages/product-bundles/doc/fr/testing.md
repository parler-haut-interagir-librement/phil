<!-- phil-dev source-sha256=d80626c0c51a7ba70b4c3b40c97a05157c490adae5a7f122b81101c7ac9c0434 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

`tests/Support/Shop` implémente les trois ports de composants avec des tableaux : prix et coûts en cents canadiens,
unités en stock.

## Le flux fonctionnel

`tests/Functional/BundleFlowTest` mène un ensemble de départ de sa définition à son retour — prix, stock, coût et marge
lus, une vente figée et son stock prélevé, la ligne stockée en JSON et relue, un lot entier retourné — puis un lot
coopératif de trois créateurs approuvé, stocké, relu, et sa première vente partagée.

## Le fuzzing

`tests/Fuzz/product-bundles-stored.php` lit chaque entrée comme un lot stocké, une ligne vendue et un lot coopératif en
JSON. Chacun donne une valeur ou `InvalidBundle`, jamais une autre exception, et une ligne relue écrit le même tableau :

```sh
composer fuzz -- product-bundles-stored
```

## Les bancs d'essai

`tests/Benchmark/BundleBench` mesure un lot de 50 composants évalué, son stock et sa marge lus, une vente figée et sa
ligne stockée relue :

```sh
composer bench -- packages/product-bundles/tests/Benchmark
```
