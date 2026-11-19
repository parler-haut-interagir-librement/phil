<!-- phil-dev source-sha256=cbd0a117cde5d46b7eb17599313bd330bd54a1eec95c524b10c5c97c67ae912a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle teste le modèle
et ses refus ; chaque mécanisme sur des problèmes construits pour montrer ce qui le distingue — un premier choix
disputé, un choix perdu dans les rondes mais obtenu en dictature en série, l'ordre des rondes en alternance, les
groupes, les paires forcées et interdites, les fermetures en cascade — ; le fait que l'ordre de l'entrée ne change
jamais le résultat ; les identifiants numériques ; le budget de travail ; et l'optimisation contre une recherche
exhaustive sur 150 problèmes aléatoires, pour les deux coûts de rang. Dans le monodépôt, Infection maintient le paquet
à 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/AssignmentBench.php` mesure mille personnes qui classent cinq créneaux parmi cent de dix places, les
créneaux populaires étant bien plus demandés que les autres : les rondes, la dictature en série et l'optimisation, sans
minimum et avec un minimum de trois places, qui ferme des créneaux et résout de nouveau. Dans le monodépôt,
`composer bench` l'exécute.

## La cible de fuzzing

`tests/Fuzz/problem.php` donne à `ProblemReader` du texte que personne n'a écrit, puis exécute les quatre assigneurs —
les rondes dans les deux ordres, la dictature en série, l'optimisation — sur chaque problème qu'il lit. Le lecteur doit
renvoyer un problème ou lancer `InvalidProblem` ; un assigneur doit renvoyer un résultat ou lancer `BudgetExceeded` ;
chaque résultat doit respecter les capacités, placer chaque candidat au plus une fois, jamais dans un créneau interdit
ou fermé, et chaque candidat forcé dans son créneau. Ses germes sont dans `tests/Fuzz/corpus/problem` ; dans le
monodépôt, `composer fuzz -- problem` l'exécute.

## Tester du code qui affecte

Utilisez un germe fixe, pour que le résultat soit le même à chaque exécution :

```php
$result = new RankedRounds()->assign($problem, Seed::fromString('test'));

self::assertSame('table-1', $result->placementOf('u-104')?->slot);
```

Pour tester ce qui arrive au candidat tiré en premier ou en dernier, lisez l'ordre dans `$result->drawOrder` plutôt que
de le deviner à partir du germe.
