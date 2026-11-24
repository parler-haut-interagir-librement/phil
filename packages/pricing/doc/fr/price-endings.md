<!-- phil-dev source-sha256=d3a8ef83867979f2377ccda855b204862829f109aa06f8bfce1c6de01514b168 -->
# Terminaisons de prix

Un prix calculé à partir d'un coût se termine rarement bien : 9.24, 13.87. Une `PriceEnding` le déplace vers un prix
qui se termine comme l'affiche une tablette.

## Pas et terminaisons

Une terminaison est un pas et un décalage : les prix qu'elle permet se répètent à chaque pas, au décalage.

| Pas | Terminaison | Prix permis |
|---|---|---|
| 1.00 | 0.99 | 0.99, 1.99, 2.99... |
| 1.00 | 0.95 | 0.95, 1.95, 2.95... |
| 1.00 | 0.00 | les unités entières |
| 10.00 | 9.99 | 9.99, 19.99, 29.99... |
| 0.50 | 0.49 | 0.49, 0.99, 1.49, 1.99... |
| 100 (yens) | 80 | 80, 180, 280... |

```php
$ninetyNine = PriceEnding::ofDecimals('1', '0.99', $cad);
// or PriceEnding::of(Money::ofDecimal('1', $cad), Money::ofDecimal('0.99', $cad))

$ninetyNine->apply(Money::ofDecimal('9.24', $cad))->toDecimal(); // "9.99"
```

Le pas est supérieur à zéro et la terminaison va de zéro à moins que le pas, dans la même devise ; sinon
`InvalidPricing` ou `CurrencyMismatch` est levée.

## Vers le haut, au plus proche ou vers le bas

Le troisième argument est un `\RoundingMode` :

| Mode | Comportement | 19.37 avec .99 |
|---|---|---|
| `PositiveInfinity` (la valeur par défaut) | Vers le haut : le premier prix permis égal ou supérieur | 19.99 |
| `HalfAwayFromZero` | Le plus proche ; une égalité va vers le haut | 18.99 |
| `NegativeInfinity` | Vers le bas : le dernier prix permis égal ou inférieur | 18.99 |

Le haut est la valeur par défaut parce qu'un prix calculé à partir d'un coût ne devrait pas perdre de marge à cause de
sa terminaison.

## Le plancher

`apply($price, $floor)` ne retourne jamais un prix sous le plancher, ni sous zéro. Quand le mode descendrait plus bas,
on prend le premier prix permis égal ou supérieur au plancher. Le plancher est d'ordinaire le coût, ou le prix de la
marge la plus basse que l'application accepte :

```php
$nearest = PriceEnding::ofDecimals('1', '0.99', $cad, RoundingMode::HalfAwayFromZero);

$nearest->apply(Money::ofDecimal('19.37', $cad))->toDecimal();                                // "18.99"
$nearest->apply(Money::ofDecimal('19.37', $cad), Money::ofDecimal('19.20', $cad))->toDecimal(); // "19.99"
```

Un prix nul se termine sur le premier prix permis, 0.99 avec la règle ci-dessus : une application garde les articles
gratuits hors de la règle. Un prix négatif lève `InvalidPricing` : un remboursement nie le prix terminé plutôt que de
terminer un prix négatif.

## Tranches

`PriceEndingBands` choisit la terminaison par tranche de prix :

```php
$endings = PriceEndingBands::startingWith(PriceEnding::ofDecimals('1', '0.99', $cad))
    ->from(Money::ofDecimal('10', $cad), PriceEnding::ofDecimals('1', '0.95', $cad))
    ->from(Money::ofDecimal('100', $cad), PriceEnding::ofDecimals('1', '0', $cad));

$endings->apply(Money::ofDecimal('9.24', $cad));   // 9.99
$endings->apply(Money::ofDecimal('13.87', $cad));  // 13.95
$endings->apply(Money::ofDecimal('123.45', $cad)); // 124.00
```

La première règle couvre tous les prix sous le premier seuil. Les tranches s'ajoutent par ordre croissant de seuils,
chacun au-dessus de zéro et au-dessus du précédent ; `from()` retourne un nouvel ensemble et laisse l'original
intact. La tranche est choisie selon le prix avant sa terminaison, et sa règle le termine même quand le résultat passe
dans la tranche suivante.

`PriceEnding` et `PriceEndingBands` implémentent toutes deux `PriceEndingRule` : une tranche peut donc contenir un autre
ensemble de tranches, ou une règle propre à l'application.

Un prix de détail suggéré se termine de la même façon, par `SuggestedRetailPrice::ended()` : voir
[Prix de détail suggérés et prix de gros](suggested-retail-prices.md). Le garder sur un produit relève d'un catalogue.
