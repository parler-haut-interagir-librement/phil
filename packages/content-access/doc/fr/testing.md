<!-- phil-dev source-sha256=a3763905b250d2a81058b7d3dabe6f989f64cef220e8402f0b3eb6da7bfac2e0 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau ; l'horloge est une
`MockClock` de `symfony/clock`. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/policy-reader.php` donne au lecteur de politiques des documents que personne n'a écrits, à partir des
germes de `tests/Fuzz/corpus/policy-reader` : quoi qu'il lise, il donne une politique ou refuse avec `InvalidPolicy`,
sans jamais lancer autre chose ni émettre d'avertissement ; une politique qu'il donne s'écrit sous une forme canonique
qui se relit à l'identique, et se décide pour un membre et pour un visiteur sans rien lancer. Dans le monodépôt,
`composer fuzz` l'exécute.

## Le banc d'essai

`tests/Benchmark/DecisionBench.php` mesure les deux chemins critiques d'une liste : lire une politique stockée d'une
douzaine de conditions, et la décider pour un membre qui détient vingt droits. Dans le monodépôt, `composer bench`
l'exécute.
