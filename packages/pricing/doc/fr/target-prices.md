<!-- phil-dev source-sha256=fbaaf8e326804e13fd122e92a20e7f61832ce073763ec7be56740fadce368612 -->
# Prix cibles

`PriceCalculator` donne le prix qui atteint une cible à partir d'un coût :

| Méthode | Formule | Exemple |
|---|---|---|
| `priceForMargin($cost, Rate $margin)` | coût / (1 − marge) | 6.00 à 40 % → 10.00 |
| `priceForMarkup($cost, Rate $markup)` | coût × (1 + majoration) | 6.00 à 50 % → 9.00 |
| `priceForCostShare($cost, Rate $share)` | coût / part | 3.00 à 30 % → 10.00 |

La part du coût est la marge cible dite dans l'autre sens, comme le dit une cuisine pour son coût des aliments : une
part du coût de 30 % est une marge de 70 %.

## Arrondir vers le haut

Les prix arrondissent en s'éloignant de zéro par défaut. Sur un coût positif, c'est vers le haut : le prix est le plus
petit qui atteint la cible, de sorte que la marge ou la majoration qu'il donne n'est jamais inférieure à celle-ci.
6.00 à une marge de 35 % fait exactement 9,2308 ; le prix est 9.24, dont la marge est 35,06 %, alors que 9.23
donnerait 34,99 %.

```php
$prices = new PriceCalculator();

$prices->priceForMargin($cost, Rate::ofPercent('35'))->toDecimal();                                 // "9.24"
$prices->priceForMargin($cost, Rate::ofPercent('35'), RoundingMode::HalfAwayFromZero)->toDecimal(); // "9.23"
```

Sur un coût nié, le prix est la négation de celui de la vente : un remboursement établi ainsi renverse exactement la
vente.

## Ce qui est refusé

| Cible | Refusé | Exception |
|---|---|---|
| Marge | 100 % ou plus : aucun prix ne laisse de place au coût | `InvalidRate` |
| Majoration | sous -100 % : le prix aurait l'autre signe ; -100 % est un prix nul | `InvalidRate` |
| Part du coût | zéro ou moins | `InvalidRate` |
| Toutes | un coût dont le produit par un million, ou par la majoration, sort de la plage des entiers natifs | `AmountOutOfRange` |

Une marge ou une majoration cible négative est acceptée : c'est un prix sous le coût, une liquidation par exemple. Le
prix garde la devise du coût.

Un prix calculé passe d'ordinaire par une [terminaison de prix](price-endings.md) avant d'arriver sur une tablette.
