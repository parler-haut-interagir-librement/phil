<!-- phil-dev source-sha256=c97c4df7b83e0e9c275405c54e60a8b73653a96d798fe96ed0e1b3e835ad4138 -->
# Marges et majorations

## Les taux

Un `Rate` est un nombre entier de parties par million : `1_000_000` vaut 100 %, `400_000` vaut 40 %, `99_750` vaut
9,975 %. Ce n'est jamais un nombre à virgule flottante.

| Constructeur | Exemple | Parties par million |
|---|---|---|
| `Rate::of(int)` | `Rate::of(400_000)` | 400 000 |
| `Rate::ofPercent(string)` | `Rate::ofPercent('9.975')`, `Rate::ofPercent('-12.5')` | 99 750, -125 000 |
| `Rate::ofBasisPoints(int)` | `Rate::ofBasisPoints(125)` | 12 500 |
| `Rate::zero()` | | 0 |

`ofPercent()` lit des chiffres avec un `-` initial facultatif et jusqu'à quatre décimales ; tout le reste — un signe
`%`, une virgule, un `+`, une cinquième décimale — lève `InvalidRate`. `percent()` réaffiche le taux sans zéros
finaux : `"40"`, `"66.6667"`, `"-12.5"`. `equals()`, `compare()` et `isNegative()` comparent les taux.

Un taux peut être négatif ou dépasser 100 % : une vente sous le coût a une marge négative, une majoration de 150 % est
courante. Quand un taux n'a aucun sens, c'est l'opération qui le reçoit qui le refuse, jamais `Rate` lui-même.

## Marge, taux de marge et majoration

`MarginCalculator` reçoit un prix et un coût :

| Méthode | Formule | 10.00 sur un coût de 6.00 |
|---|---|---|
| `margin($price, $cost)` | prix − coût | 4.00 |
| `marginRate($price, $cost)` | (prix − coût) / prix | 40 % |
| `markupRate($price, $cost)` | (prix − coût) / coût | 66,6667 % |

Le prix est ce que le client paie par unité avant les taxes et après les remises. Les taux arrondissent au plus proche
en s'éloignant de zéro pour les demi-valeurs par défaut ; n'importe quel `\RoundingMode` peut être passé en dernier
argument :

```php
$margins = new MarginCalculator();

$margins->markupRate($price, $cost)->percent();                            // "66.6667"
$margins->markupRate($price, $cost, RoundingMode::TowardsZero)->percent(); // "66.6666"
```

Le taux de marge d'un prix nul et la majoration sur un coût nul sont indéfinis et lèvent `UndefinedRate` ; la
bibliothèque n'invente jamais de valeur pour eux. Un coût nul a un taux de marge de 100 % ; un prix nul sur un coût
positif a une majoration de -100 %.

## Convertir un taux de marge et une majoration

| Méthode | Formule | Exemple |
|---|---|---|
| `markupFromMargin(Rate $margin)` | marge / (1 − marge) | 40 % → 66,6667 % |
| `marginFromMarkup(Rate $markup)` | majoration / (1 + majoration) | 100 % → 50 % |

Une marge de 100 % ou plus ne laisse rien pour le coût et lève `InvalidRate`. Une majoration de -100 % est un prix nul
et une majoration inférieure est un prix de l'autre signe : les deux lèvent `InvalidRate`.

Les conversions sont exactes à l'arrondi près. Une marge de 0 à 100 % convertie en majoration puis reconvertie revient
inchangée ; une marge négative, ou une majoration de 0 à 100 % convertie en marge puis reconvertie, revient à deux
parties par million près. Les tests de propriétés vérifient ces bornes sur des milliers de taux générés.

## Signes et remboursements

Un remboursement nie le prix et le coût ; sa marge est niée et ses taux sont ceux de la vente. Un prix et un coût de
signes opposés — un prix positif sur un coût négatif — ne décrivent aucune vente et lèvent `InvalidPricing`. L'un ou
l'autre peut être nul : un article gratuit a un prix nul, un article donné a un coût nul.

## La marge de plusieurs lignes

Une `PricedLine` est un prix unitaire, un coût unitaire et une quantité ; son `revenue` et son `totalCost` sont tous
deux multipliés par la quantité, et `margin()` est leur différence. Une quantité négative est un retour.

`MarginCalculator::summarize()` additionne une ou plusieurs lignes dans un `MarginSummary` composé de `revenue`, de
`cost` et de `margin`, dont `marginRate()` et `markupRate()` sont calculés sur ces totaux :

```php
$summary = $margins->summarize(
    PricedLine::of(Money::ofDecimal('1.00', $cad), Money::ofDecimal('0.10', $cad)),
    PricedLine::of(Money::ofDecimal('100.00', $cad), Money::ofDecimal('90.00', $cad)),
);

$summary->marginRate()->percent(); // "10.7921", not "50", the average of 90 % and 10 %
```

Chaque ligne pèse selon ses montants : la marge d'une catégorie est sa marge totale sur son revenu total, jamais la
moyenne des taux de ses produits. `summarize()` prend au moins une ligne ; étalez une liste avec `...$lines`. Des
lignes en devises différentes lèvent `CurrencyMismatch`.
