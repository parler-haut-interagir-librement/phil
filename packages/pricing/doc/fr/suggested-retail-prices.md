<!-- phil-dev source-sha256=91b02bd3fb36eb0372584f5356d3233637258122f362c2a8b4296957b7a5a8cc -->
# Prix de détail suggérés et prix de gros

Un fournisseur vend à un détaillant à un prix de gros et suggère souvent le prix auquel le produit devrait se vendre :
le prix de détail suggéré (PDSF). `SuggestedRetailPrice` en tient un, avec sa provenance ; `RetailPriceComparison`
dit à quelle distance un prix de vente en est ; `WholesalePriceList` établit le prix d'un produit pour les détaillants,
avec des paliers de quantité.

Chaque montant est un `Money` de `phil/money`, chaque majoration un `Rate` en parties par million, et chaque division
arrondit selon un `\RoundingMode` nommé.

## D'où vient un prix suggéré

| Constructeur | Source | Prix |
|---|---|---|
| `fromSupplier($price)` | `Supplier` | Le prix que donne le fournisseur |
| `fromCost($cost, $markup)` | `CostMarkup` | `cost × (1 + markup)` |
| `keystone($cost)` | `CostMarkup` | Le double du coût : une majoration de 100 % |
| `fromWholesale($wholesale, $markup)` | `WholesaleMarkup` | `wholesale × (1 + markup)` |

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\Rate;
use Phil\Pricing\SuggestedRetailPrice;

$cad = Currency::of('CAD');
$cost = Money::ofDecimal('6.00', $cad);

SuggestedRetailPrice::fromSupplier(Money::ofDecimal('24.99', $cad))->price->toDecimal();  // "24.99"
SuggestedRetailPrice::fromCost($cost, Rate::ofPercent('60'))->price->toDecimal();          // "9.60"
SuggestedRetailPrice::keystone($cost)->price->toDecimal();                                  // "12.00"
SuggestedRetailPrice::keystone($cost)->isKeystone();                                        // true
SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $cad), Rate::ofPercent('100'))
    ->price->toDecimal();                                                                   // "15.60"
```

Un prix suggéré garde sa `source`, sa `basis` — le coût ou le prix de gros qu'il majore, null pour le prix d'un
fournisseur — et sa `markup`, null pour le prix d'un fournisseur. `isKeystone()` est vrai pour une majoration du coût
d'exactement 100 %.

Un prix issu d'une majoration arrondit en s'éloignant de zéro par défaut, comme les [prix cibles](target-prices.md), si
bien que la majoration est toujours atteinte : un coût de 6.37 à 35 % donne 8.5995, suggéré à 8.60. Tout
`\RoundingMode` peut être donné en dernier argument.

## Terminer un prix suggéré

`ended()` applique toute [`PriceEndingRule`](price-endings.md) et retourne un nouveau prix suggéré ; le prix d'avant la
terminaison reste dans `unendedPrice` :

```php
use Phil\Pricing\PriceEnding;

$suggested = SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $cad), Rate::ofPercent('100'))
    ->ended(PriceEnding::ofDecimals('1', '0.99', $cad));

$suggested->price->toDecimal();        // "15.99"
$suggested->unendedPrice->toDecimal(); // "15.60"
```

La terminaison ne passe jamais sous le plancher donné en deuxième argument ; sans plancher, elle ne passe pas sous le
coût ou le prix de gros dont vient le prix suggéré. Terminer un prix déjà terminé repart de `unendedPrice`. Une règle
qui termine le prix à zéro lève `InvalidPricing` : un prix suggéré est toujours au-dessus de zéro.

## Comparer un prix de vente

`compare()` retourne une `RetailPriceComparison` : la différence en argent, négative sous le prix suggéré, et le taux de
cette différence sur le prix suggéré.

```php
$comparison = SuggestedRetailPrice::fromSupplier(Money::ofDecimal('25.00', $cad))
    ->compare(Money::ofDecimal('20.00', $cad));

$comparison->difference()->toDecimal(); // "-5.00"
$comparison->rate()->percent();         // "-20"
$comparison->rateBelow()->percent();    // "20": 20 % below the suggested price
$comparison->isBelow();                 // true
```

`rate()` est signé et arrondit à la moitié en s'éloignant de zéro sauf si un `\RoundingMode` est donné : 12.79 face à
15.99 donne `"-20.0125"`. `rateBelow()` est le même taux comme nombre positif sous le prix suggéré, et zéro au prix
suggéré ou au-dessus. `isBelow()`, `isAbove()` et `isAtSuggestedPrice()` disent de quel côté on se trouve.
`RetailPriceComparison::of($selling, $suggested)` compare deux montants sans `SuggestedRetailPrice`.

## Prix de gros et paliers de quantité

`WholesalePriceList::fromCost()` majore un coût en prix de gros, le palier à partir d'une unité. `fromMarkup()` ajoute un
palier établi à partir du coût par une majoration plus basse, `from()` un palier à un prix unitaire donné ; chacun
retourne une nouvelle liste :

```php
use Phil\Pricing\WholesalePriceList;

$wholesale = WholesalePriceList::fromCost($cost, Rate::ofPercent('30')) // 7.80 a unit
    ->fromMarkup(12, Rate::ofPercent('25'))                               // 7.50 a unit from 12
    ->from(48, Money::ofDecimal('7.20', $cad));                           // 7.20 a unit from 48

$wholesale->basePrice()->toDecimal();      // "7.80"
$wholesale->unitPriceFor(11)->toDecimal(); // "7.80"
$wholesale->unitPriceFor(12)->toDecimal(); // "7.50"
$wholesale->unitPriceFor(47)->toDecimal(); // "7.50"
$wholesale->unitPriceFor(48)->toDecimal(); // "7.20"
$wholesale->totalFor(24)->toDecimal();     // "180.00"
```

Une commande atteint le dernier palier dont elle respecte la quantité ; `tierFor()` retourne ce `WholesaleTier`, avec sa
`minimumQuantity`, son `unitPrice` et sa `markup`, null pour un palier à un prix donné. Les paliers s'ajoutent dans
l'ordre croissant de leurs quantités, chacune strictement au-dessus de la précédente, et un palier ne hausse jamais le
prix unitaire : un palier au même prix que le précédent est accepté, un prix plus élevé lève `InvalidPricing`. Une
quantité sous 1 lève `InvalidPricing`.

Un prix de détail suggéré est souvent le double du prix de gros : `SuggestedRetailPrice::fromWholesale(
$wholesale->basePrice(), Rate::ofPercent('100'))`.

## Les cas refusés

| Cas | Exception |
|---|---|
| Un coût ou un prix de gros nul à majorer | `InvalidPricing` |
| Un coût ou un prix de gros négatif | `InvalidPricing` : un remboursement nie le prix, pas le coût |
| Une majoration de -100 % ou moins | `InvalidRate` |
| Le prix d'un fournisseur, ou un prix après l'arrondi ou la terminaison, qui n'est pas au-dessus de zéro | `InvalidPricing` |
| Un prix de vente négatif comparé à un prix suggéré | `InvalidPricing` : les signes sont opposés |
| Un prix de vente, un plancher, une terminaison ou un prix de palier dans une autre devise | `Phil\Money\Exception\CurrencyMismatch` |
| Un palier dont la quantité n'est pas au-dessus de la précédente, ou dont le prix est plus élevé | `InvalidPricing` |
| Un prix de palier qui n'est pas au-dessus de zéro, ou une quantité sous 1 | `InvalidPricing` |
| Un coût multiplié par une majoration hors de la plage des entiers natifs | `AmountOutOfRange` |

## Ce que la bibliothèque laisse au catalogue

La bibliothèque calcule un prix suggéré et lui compare un prix de vente ; elle ne garde rien. Garder le prix suggéré sur
un produit, avec sa source, revient à `phil/commerce-catalog-bundle`, qui le stockera. Un prix minimum annoncé, un
plancher qu'un fournisseur impose au prix annoncé, n'est pas modélisé.
