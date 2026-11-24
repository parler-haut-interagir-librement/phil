<!-- phil-dev source-sha256=fe38b055e77e303f926fbba480cd2923f326a3f951654e6427f40e588c0ce8b3 -->
# Tests

## Les tests de la bibliothèque

`tests/Unit` contient :

- `IntegerMathTest` : chacun des huit `\RoundingMode` de part et d'autre de la moitié, sur des moitiés exactes, sur les
  deux signes et sur un dénominateur négatif, comparés à `round()` sur deux mille quotients générés ; la moitié trouvée
  sans dépassement sur le plus grand dénominateur ; produits et différences hors plage refusés.
- `RateTest` : pourcentages lus et réaffichés, pourcentages mal formés refusés, points de base, comparaisons.
- `MarginCalculatorTest` : marges, taux de marge et majorations de ventes, de remboursements et de ventes sous le coût ;
  prix et coûts nuls ; signes opposés et devises refusés ; les deux conversions et leurs bornes refusées ; sommaires
  pondérés par leurs montants.
- `PriceCalculatorTest` : prix pour une marge, une majoration et une part du coût, arrondis en s'éloignant de zéro,
  dans trois devises, avec leurs cibles refusées et le plus grand coût établi.
- `DiscountCascadeTest` : étapes enchaînées, ordre conservé, arrondi par étape, remboursements, bornes, taux effectifs.
- `PriceEndingTest` : terminaisons vers le haut, au plus proche et vers le bas, planchers, devises, tranches et leur
  ordre.
- `SuggestedRetailPriceTest` : les trois sources et le double du coût, leurs arrondis, des terminaisons qui gardent le
  prix non terminé et ne passent jamais sous la base ni sous le plancher, les coûts, majorations et prix refusés.
- `RetailPriceComparisonTest` : différences et taux en dessous, à et au-dessus du prix suggéré, leurs arrondis, les
  devises et les signes refusés.
- `WholesalePriceListTest` : le palier de base, les paliers par majoration et par prix, le palier que chaque quantité
  atteint, les totaux, des paliers désordonnés ou qui haussent le prix, les coûts, majorations et quantités refusés.
- `SuggestedRetailPriceDocumentationTest` : chaque exemple de
  [Prix de détail suggérés et prix de gros](suggested-retail-prices.md) avec la sortie qu'il montre.
- `PropertiesTest` : des milliers de cas générés à partir d'un germe fixe — les conversions dans les deux sens dans
  leurs bornes, chaque prix cible le plus petit qui atteint sa cible, des cascades qui s'enchaînent exactement et restent
  à une demi-unité par étape du résultat exact, des remboursements qui nient la vente, des prix terminés permis, jamais
  sous le prix ni sous le plancher et à moins d'un pas au-dessus ; des prix suggérés qui sont les plus petits à atteindre
  leur majoration, des prix suggérés terminés jamais sous le prix non terminé quand la règle arrondit vers le haut, des
  comparaisons qui s'additionnent, des prix de gros unitaires qui ne montent jamais avec la quantité.
- `ExceptionsTest` : chaque exception sous `PricingException`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/pricing/tests
```

Installée seule :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests de mutation soumettent la bibliothèque à l'exigence d'une couche de domaine : chaque mutant de `src/` est
tué.

## Tester du code qui établit des prix

- **Faites vos assertions sur les unités mineures et les parties par million** :
  `self::assertSame(924, $price->minorUnits)`, `self::assertSame(400_000, $rate->partsPerMillion)`.
- **Gardez un cas qui ne se divise pas également**, comme une marge de 35 % sur 6.00 : un calcul qui arrondit au plus
  proche plutôt que vers le haut donne 9.23 et l'échoue.
- **Gardez un cas de remboursement** : le prix et le coût niés donnent les mêmes taux et les montants niés.
- La bibliothèque n'a ni horloge, ni conteneur, ni entrée-sortie : aucune doublure n'est nécessaire.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent un sommaire de mille à deux mille lignes, une cascade de trois
remises sur chaque ligne, un prix pour une marge terminé par tranches sur chaque coût, et une liste de prix de gros avec
un palier, un prix suggéré terminé par tranches et sa comparaison sur chaque coût. Depuis la racine du monodépôt :

```sh
composer bench -- packages/pricing/tests/Benchmark
```
