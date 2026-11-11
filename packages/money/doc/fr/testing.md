<!-- phil-dev source-sha256=0ac61f7c7e4c3557c003295205e702344f9abfbab0938f4ea497f7ad42033cdd -->
# Tests

## Les tests de la bibliothèque

`tests/Unit` contient `CurrencyTest`, `MoneyTest` et `SchemaOrgTest` : les codes connus et refusés, la précision, les
noms et les symboles localisés ; l'arithmétique et la comparaison dans une devise, et leur refus entre deux devises ;
chaque dépassement, `PHP_INT_MIN` compris ; les décimaux lus et écrits pour des devises à 0, 2 et 3 décimales, jusqu'au
plus grand montant ; les répartitions avec restes, égalités, proportions nulles et montants négatifs ; le
`MonetaryAmount` et le prix d'`Offer` de schema.org, les décimales étant gardées sous forme de texte.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/money/tests
```

Installée seule :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests de mutation tiennent la bibliothèque au seuil d'une couche de domaine : chaque mutant de `src/` est tué.

## Tester du code qui compte de l'argent

- **Faites vos assertions sur les unités mineures**, pas sur les décimaux ni sur des chaînes mises en forme :
  `self::assertSame(1_234, $total->minorUnits)`.
- **Gardez une devise de chaque précision** dans les cas qui calculent : `JPY` (0), `CAD` (2), `BHD` (3). Un calcul qui
  suppose 2 décimales échoue sur le yen.
- **Vérifiez que les parts d'une répartition donnent le tout** une fois additionnées :
  `Money::sum(...$parts)->equals($amount)`.
- `Money` n'a ni horloge, ni conteneur, ni entrée-sortie : aucune doublure n'est nécessaire.
