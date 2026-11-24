<!-- phil-dev source-sha256=89407a1ce753f1536e44fdbfeeb8218efe402ff85e80e070c084069801d584c0 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port, et ses classes sont finales. Elle calcule ;
ce qu'il faut établir comme prix, quelles remises s'appliquent et quelles terminaisons une boutique emploie reviennent
à l'application, et parviennent à la bibliothèque en arguments.

| Point | Comment |
|---|---|
| Arrondi | Chaque méthode qui divise prend un `\RoundingMode` en dernier argument — voir [Arrondis et limites](rounding.md) |
| Marges, majorations et remises cibles | Des valeurs `Rate` que l'application construit à partir de ses réglages avec `Rate::of()`, `ofPercent()` ou `ofBasisPoints()` |
| Quelles remises s'appliquent, dans quel ordre | La liste donnée à `DiscountCascade::apply()` ; un moteur de promotions la décide, la cascade garde son ordre |
| Terminaisons de prix | `PriceEnding` pour une règle, `PriceEndingBands` pour des règles par tranche de prix, ou une classe propre à l'application qui implémente `Phil\Pricing\Contract\PriceEndingRule` — une table de prix choisis à la main, par exemple ; les tranches acceptent toute `PriceEndingRule` |
| Planchers | Le `$floor` de `PriceEndingRule::apply()` : le coût, ou un prix de `PriceCalculator` à la marge la plus basse acceptée |
| Prix suggérés | `SuggestedRetailPrice` à partir de la liste du fournisseur, d'une majoration du coût, du double du coût ou d'un prix de gros ; l'application garde le résultat, sur un produit par exemple |
| Paliers de quantité | `WholesalePriceList::fromMarkup()` et `from()`, ajoutés dans l'ordre croissant de leurs quantités |
| Lignes d'un rapport | `PricedLine::of()` à partir des ventes de l'application ; `MarginCalculator::summarize()` les additionne |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Pricing\Exception\PricingException` : un seul `catch` couvre `InvalidRate`, `InvalidPricing`, `UndefinedRate` et `AmountOutOfRange` ; `CurrencyMismatch` et `InvalidAmount` viennent de `phil/money` |

`PriceEndingRule` est stable au sein d'une version mineure : une implémentation reçoit un prix et un plancher facultatif
et retourne un prix dans la même devise, jamais négatif et jamais sous le plancher.
