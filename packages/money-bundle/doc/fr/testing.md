<!-- phil-dev source-sha256=ef3a81dd3a4335528e882bcf967ba403a9019948f4fa14fe3b7bbcb5ebbef5da -->
# Tests

## Les tests du bundle

`tests/Unit` teste chaque classe seule : les devises acceptées, le décimal sensible à la locale, le type de formulaire
par une fabrique de formulaires, chaque contrainte avec le `ConstraintValidatorTestCase` de Symfony, le filtre Twig dans
un environnement, le type de colonne sur une plateforme, ainsi que la configuration, les services et la configuration
Doctrine ajoutée en préfixe du bundle.

`tests/Functional` démarre un noyau en `fr_CA` avec FrameworkBundle, TwigBundle et DoctrineBundle sur SQLite : un prix
saisi dans un formulaire, les contraintes avec leurs messages français, le filtre, et un `Product` dont le prix est
stocké dans deux colonnes, relu et additionné.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/money-bundle/tests
```

Installé seul :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests de mutation tuent chaque mutant de `src/`.

## Tester les formulaires et les entités d'une application

- **Fixez la locale** dans laquelle lit le test : `Locale::setDefault('fr_CA')`, restaurée après le test. Un montant
  tapé `1 234,50` se lit autrement en `en`.
- **Soumettez des chaînes**, comme le fait un navigateur : `['amount' => '19,99', 'currency' => 'CAD']`.
- **Comparez les montants avec `equals()`** ou sur `minorUnits`, pas avec `assertSame()` sur deux objets.
- **Gardez un test d'aller-retour d'entité** quand un `Money` intégré est ajouté : il détecte une association ou un type
  de colonne manquant avant la production.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `format_money` sur des listes de un à cinq cents prix, avec le
formateur conservé et avec un nouveau formateur par prix. Depuis la racine du monodépôt :

```sh
composer bench -- packages/money-bundle/tests/Benchmark
```
