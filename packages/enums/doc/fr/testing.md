<!-- phil-dev source-sha256=fdd4a9080f3ad76bac0bab09e0ffba2fd3641fc46fb235e31748bdbe28a4c98e -->
# Tests

## Les tests de la bibliothèque

`tests/Unit` contient une classe de test par trait, un test d'`AbstractMetaProperty` et `Meta\CoverageTest`, qui fixe ce
que les tests des traits laissent de côté : les messages d'erreur exacts, les noms d'accesseurs personnalisés, les
propriétés méta déclarées par un trait, une enum à un seul cas, et les libellés par défaut de `stringOptions()` pour
chaque forme de nom de cas, lettres hors ASCII comprises. Les jeux de données d'essai sous `tests/Fixture` sont des
enums et des propriétés méta construites pour ces cas.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/enums/tests
```

Lorsque la bibliothèque est installée seule :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests de mutation maintiennent la bibliothèque au même seuil qu'une couche de domaine : chaque mutant de `src/` hors
des extensions PHPStan est tué. Les mutants excusés dans la configuration sont ceux qu'aucun test ne peut voir : un
changement de visibilité de `customMethodName()`, les caches des propriétés méta, des cas obsolètes et des transitions,
qui contiennent des réponses qu'une nouvelle lecture donne inchangées, et le filtre des alias en chaînes, qui ne sert
qu'à indiquer leur type à PHPStan.

Les règles PHPStan sont vérifiées par l'analyse elle-même : les jeux de données d'essai `Incomplete`, `Misdeclared`,
`OrdinalLeak`, `TwoDefaults` et `Level` portent des commentaires `@phpstan-ignore` qui nomment les identifiants qu'ils
doivent déclencher, et un ignore qui ne correspond à rien fait échouer l'analyse.

## Tester vos enums

Les traits n'exigent aucune préparation : appelez les méthodes sur votre enum dans un simple test PHPUnit. Pour les
métadonnées, vérifiez l'accesseur de chaque cas, la valeur par défaut d'un cas sans l'attribut, et `fromMeta()` sur une
valeur qu'aucun cas ne porte.

Pour les métadonnées requises, vérifiez qu'il n'en manque à aucun cas, afin qu'un cas ajouté sans son libellé fasse
échouer la suite :

```php
self::assertSame([], Reflection::missingRequiredMeta(Status::class));
```

Pour `random()`, passez un `Randomizer` sur un moteur avec germe — `new Randomizer(new Xoshiro256StarStar(42))` — et
vérifiez le tirage qu'il donne ; ou un moteur de votre cru qui distribue des nombres choisis, pour vérifier le cas sur
lequel tombe chaque tirage. Pour une `EnumMap` construite par `total()`, la construire dans un test suffit : un cas que
la fonction de rappel oublie y échoue.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/enums/tests/Benchmark
```
