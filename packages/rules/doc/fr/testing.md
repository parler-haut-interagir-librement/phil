<!-- phil-dev source-sha256=baca8576d596add15c5a075d871c10c0dffd6de10d1a8ac95ea496b70d774e05 -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit sous `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. `tests/Support/Recorder` est un type de condition qui
enregistre les feuilles évaluées, de sorte que les tests voient ce qu'un court-circuit a sauté. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/rule-reader.php` donne au lecteur des règles que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/rule-reader` : quoi qu'il lise, il donne une règle ou refuse avec `InvalidRule` ou `LimitExceeded` ;
une règle qu'il donne écrit une forme canonique qui se relit à l'identique et s'évalue sans lancer d'exception.

```sh
composer fuzz -- rules
```

## Le banc d'essai

`tests/Benchmark/RuleBench` lit une règle stockée d'environ soixante-dix conditions et l'évalue pour un sujet, le
chemin qu'un segment ou une liste de prix emprunte une fois par personne ou par ligne :

```sh
composer bench -- packages/rules/tests/Benchmark
```

## Tester un type de condition d'une application

Un type de condition se teste au travers d'une règle, de sorte que son schéma soit vérifié comme le serait une règle
stockée :

```php
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

$rule = new Rule(new Leaf('cart.total', ['at_least_cents' => 5000, 'currency' => 'CAD']), new ConditionTypes(new CartTotal()));
$result = $rule->evaluate(Facts::of(['cart' => $cart]));

self::assertFalse($result->passed);
self::assertSame('cart.too-low', $result->trace->reason);
```
