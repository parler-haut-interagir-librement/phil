<!-- phil-dev source-sha256=9745aca9c0c16136abe3690852d884034c5aec72efc7bbd15b979757706bdd14 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque détecteur sur ses motifs et leurs essais, la recherche de la couverture avec des
correspondances faites à la main, la rétroaction de chaque motif, l'estimateur de bout en bout, la source des fuites
contre un client HTTP simulé, les générateurs avec des générateurs aléatoires à germe, et les deux contraintes avec le
`ConstraintValidatorTestCase` de Symfony. Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/passwords/tests
```

Infection exige de la bibliothèque un score de mutation couvert de 100 %.

## Fuzzing

La cible de fuzzing `strength-estimator` donne à l'estimateur des entrées que personne n'a écrites, en UTF-8 valide
ou non ; `range-response` les donne au lecteur des plages de Pwned Passwords, qui retourne des occurrences ou lance
`BreachCheckUnavailable` :

```sh
composer fuzz -- strength-estimator range-response
```

## Le banc d'essai

`tests/Benchmark/StrengthEstimatorBench.php` mesure l'estimateur sur des mots de passe ordinaires, une phrase de passe
et les entrées les plus hostiles de 128 caractères — une lettre répétée, un mot de passe courant répété, des chiffres
lus comme des dates partout —, ainsi que la construction de l'estimateur :

```sh
composer bench -- packages/passwords/tests/Benchmark
```

Un mot de passe ordinaire prend une fraction de milliseconde ; l'entrée la plus hostile reste sous quelques dizaines
de millisecondes.

## Tester du code qui utilise la bibliothèque

L'estimateur est déterministe une fois son année de référence donnée : construisez-le avec `referenceYear` pour que
les dates et les années coûtent la même chose quelle que soit l'année où le test s'exécute. Donnez au vérificateur de
fuites un `InMemoryBreachSource`, et à `PwnedPasswordsSource` un `MockHttpClient` :

```php
$checker = new BreachChecker(InMemoryBreachSource::fromPasswords(['hunter2' => 17]));

$source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse('', ['http_code' => 503])));
```

Donnez aux générateurs un générateur aléatoire avec germe, `new Randomizer(new Mt19937(42))`, pour une sortie
reproductible.
