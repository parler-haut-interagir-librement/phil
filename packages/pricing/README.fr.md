<!-- phil-dev source-sha256=0a02c2d85c49286d85645e87c35940389fcf21f92e3245e9e3668181319c6b71 -->
# phil/pricing

**Des prix à partir des coûts, des marges et des remises, exacts à l'unité, sans aucun nombre à virgule flottante.**

phil/pricing calcule ce dont une boutique a besoin pour fixer ses prix : la marge et la majoration d'une vente, la
conversion d'un taux en l'autre, le prix qui atteint une marge ou une majoration cible, des remises successives
appliquées l'une après l'autre, la marge combinée de plusieurs lignes, la terminaison d'un prix calculé sur .99, .95
ou une unité entière, les prix de détail suggérés et l'écart d'un prix de vente avec l'un d'eux, et les prix de gros
avec des paliers de quantité. Il ne garde pas les coûts, ne décide pas quelles remises s'appliquent et ne connaît aucun
catalogue : cela revient à l'application, qui lui passe les montants et les taux.

Il sert à tout code PHP qui fixe des prix à partir de coûts ou qui rapporte des marges : une importation de catalogue
qui établit le prix de chaque produit à partir du coût de son fournisseur, un point de vente qui affiche la marge d'un
panier, un rapport sur la marge d'une catégorie.

C'est une bibliothèque PHP simple dont la seule dépendance est `phil/money`, dont elle reçoit et retourne les `Money`.
Les taux sont gardés en parties par million dans un `Rate`, jamais dans un nombre à virgule flottante. Chaque division
dit comment elle arrondit avec le `\RoundingMode` de PHP : les taux arrondissent au plus proche en s'éloignant de zéro
pour les demi-valeurs, les prix cibles arrondissent en s'éloignant de zéro pour que la cible soit toujours atteinte,
les terminaisons arrondissent vers le haut par défaut.

## État

| Partie | État |
|---|---|
| `Rate` : parties par million, lu et affiché en pourcentage, points de base | Implémenté et testé |
| `MarginCalculator` : marge, taux de marge, majoration, conversions dans les deux sens, sommaire de plusieurs lignes | Implémenté et testé |
| `PricedLine` et `MarginSummary` : lignes pondérées par leurs montants | Implémenté et testé |
| `PriceCalculator` : prix à partir d'une marge, d'une majoration ou d'une part du coût cibles | Implémenté et testé |
| `DiscountCascade` : remises successives, chaque étape exposée, taux effectif | Implémenté et testé |
| `PriceEnding` et `PriceEndingBands` : terminaisons comme .99, vers le haut ou au plus proche, jamais sous un plancher, par tranche de prix | Implémenté et testé |
| `SuggestedRetailPrice` : à partir du fournisseur, d'une majoration du coût, du double du coût ou d'un prix de gros ; terminé ; comparé à un prix de vente | Implémenté et testé |
| `WholesalePriceList` : prix de gros à partir d'un coût et d'une majoration, paliers de quantité qui ne haussent jamais le prix | Implémenté et testé |
| Exceptions sous une seule interface `PricingException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Marges et majorations](doc/fr/margins.md) | `Rate`, `MarginCalculator`, `PricedLine` et `MarginSummary` : les taux, leurs conversions, la marge combinée. |
| [Prix cibles](doc/fr/target-prices.md) | `PriceCalculator` : le prix pour une marge, une majoration ou une part du coût, et pourquoi il arrondit vers le haut. |
| [Remises](doc/fr/discounts.md) | `DiscountCascade` : l'ordre, l'arrondi de chaque étape, le taux effectif. |
| [Terminaisons de prix](doc/fr/price-endings.md) | `PriceEnding` et `PriceEndingBands` : .99, .95, unités entières, modes, planchers, tranches. |
| [Prix de détail suggérés et prix de gros](doc/fr/suggested-retail-prices.md) | `SuggestedRetailPrice`, `RetailPriceComparison` et `WholesalePriceList` : sources, double du coût, terminaisons, comparaisons, paliers de quantité. |
| [Arrondis et limites](doc/fr/rounding.md) | Chaque arrondi de la bibliothèque, les signes qu'elle accepte et les plus grands montants. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application choisit, remplace ou ajoute. |
| [Tests](doc/fr/testing.md) | Les tests, les propriétés et les bancs d'essai de la bibliothèque, et comment tester du code qui établit des prix. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/pricing
```

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\MarginCalculator;
use Phil\Pricing\PriceCalculator;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\Rate;
use Phil\Pricing\SuggestedRetailPrice;

$cad = Currency::of('CAD');
$cost = Money::ofDecimal('6.00', $cad);

$margins = new MarginCalculator();
$margins->marginRate(Money::ofDecimal('10.00', $cad), $cost)->percent(); // "40"
$margins->markupRate(Money::ofDecimal('10.00', $cad), $cost)->percent(); // "66.6667"
$margins->markupFromMargin(Rate::ofPercent('40'))->percent();          // "66.6667"

$price = new PriceCalculator()->priceForMargin($cost, Rate::ofPercent('35')); // 9.24, never under 35 %
$shelf = PriceEnding::ofDecimals('1', '0.99', $cad)->apply($price, $cost);  // 9.99

$sale = new DiscountCascade()->apply($shelf, [Rate::ofPercent('20'), Rate::ofPercent('10')]);
$sale->price->toDecimal();                 // "7.19": 9.99 less 2.00, then less 0.80
$sale->effectiveRate()->percent();         // "28.028"

$suggested = SuggestedRetailPrice::keystone($cost)->ended(PriceEnding::ofDecimals('1', '0.99', $cad)); // 12.99
$suggested->compare($sale->price)->rateBelow()->percent(); // "44.6497": 44.65 % below the suggested price
```

## Prérequis

PHP 8.5 ou une version plus récente, et `phil/money` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
