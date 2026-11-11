<!-- phil-dev source-sha256=0c4d1642629f49e43bd9a5801c3fc03834833627bc41430ce0c93b820ead4585 -->
# Ventilations

Une vente peut être soumise à plusieurs taxes à la fois, chacune sur la même base, chacune arrondie sur sa propre ligne
et détaillée sur la facture. Un modèle à taux unique retourne un seul taux par vente et ne peut pas l'exprimer.

| Type | Rôle |
|---|---|
| `TaxLine` | Une ligne : un taux, sa base, son montant |
| `TaxBreakdown` | Plusieurs lignes sur la même base ; totaux et inversion |
| `MultiRateTaxCalculator` | Applique chaque taux à la base et retourne la ventilation, ou retire les taxes d'un prix qui les inclut |

## Exemple

```php
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxRate;

$base = Money::of(1_999, Currency::of('CAD')); // 19.99

$breakdown = new MultiRateTaxCalculator()->calculate($base, [
    TaxRate::of('GST', 50_000), // 5 %
    TaxRate::of('QST', 99_750), // 9.975 %
]);

$breakdown->lines[0]->amount->minorUnits;    // 100  (99.95 rounded)
$breakdown->line('QST')?->amount->minorUnits; // 199  (199.40 rounded), on 19.99, not on 19.99 + GST
$breakdown->totalTax()->minorUnits;          // 299
$breakdown->totalWithTax()->minorUnits;      // 2298

$credit = $breakdown->reversed();            // base -1999, lines -100 and -199
```

## `TaxLine`

| Membre | Comportement |
|---|---|
| `static computed(TaxRate $rate, Money $base): self` | Montant calculé par `$rate->taxOn($base)` |
| `static reported(TaxRate $rate, Money $base, Money $amount): self` | Montant tel qu'un moteur de taxation externe l'a fourni, conservé tel quel ; `CurrencyMismatch` quand le montant et la base sont dans des devises différentes |
| `public TaxRate $rate`, `public Money $base`, `public Money $amount` | La valeur |
| `reversed(): self` | Même taux, base et montant de signe opposé |

`reported()` existe pour les applications qui délèguent la taxation à un moteur externe : la bibliothèque ne fait alors
que détailler ce que le moteur a calculé, sans le recalculer.

## `TaxBreakdown`

| Membre | Comportement |
|---|---|
| `static of(Money $base, list<TaxLine> $lines): self` | Vérifie que chaque ligne porte exactement sur cette base et qu'un code de taux apparaît au plus une fois |
| `public Money $base`, `public list<TaxLine> $lines` | La valeur, les lignes dans l'ordre donné |
| `line(string $code): ?TaxLine` | La ligne d'un code de taux, ou `null` |
| `totalTax(): Money` | La somme des montants des lignes ; zéro dans la devise de la base quand il n'y a aucune ligne |
| `totalWithTax(): Money` | La base plus le total des taxes |
| `reversed(): self` | La ventilation d'une note de crédit : la base et chaque ligne de signe opposé |

Refus de `of()` :

| Cas | Exception |
|---|---|
| La base d'une ligne est dans une autre devise | `Phil\Money\Exception\CurrencyMismatch` |
| La base d'une ligne est un autre montant | `InvalidBreakdown` : chaque taux d'une ventilation s'applique à la même base |
| Un code de taux apparaît deux fois | `InvalidBreakdown` |

- **Chaque taux s'applique à la même base, jamais à la base plus une autre taxe.** La taxe sur taxe est une autre
  règle et exigerait un autre type.
- **Chaque ligne est arrondie séparément**, comme le reçu l'affiche, jamais la somme des taux en une fois : deux taux
  de 5 % sur 0,10 donnent 1 cent chacun, 2 cents en tout, là où arrondir la somme en une fois donnerait 1.
- **Une note de crédit inverse les taxes détaillées** avec `reversed()` plutôt que de modifier la facture.

## `MultiRateTaxCalculator`

```php
final readonly class MultiRateTaxCalculator
{
    /** @param list<TaxRate> $rates */
    public function calculate(Money $base, array $rates): TaxBreakdown;

    /** @param list<TaxRate> $rates */
    public function extract(Money $price, array $rates): TaxBreakdown;
}
```

`calculate()` donne une ligne calculée par taux, dans l'ordre donné ; aucun taux donne une ventilation vide.
`extract()` retire les taxes d'un prix qui les inclut, comme le montre la section suivante. Sans état, sans argument de
constructeur.

## Prix qui incluent leurs taxes

Là où le prix affiché est le prix payé, `extract()` en retire les taxes au lieu de les ajouter :

```php
$breakdown = new MultiRateTaxCalculator()->extract(Money::of(1_000, Currency::of('CAD')), [
    TaxRate::of('GST', 50_000),
    TaxRate::of('QST', 99_750),
]);

$breakdown->lines[0]->amount->minorUnits;  // 44   (5 % of 8.70)
$breakdown->lines[1]->amount->minorUnits;  // 87   (9.975 % of 8.70)
$breakdown->base->minorUnits;              // 869  (10.00 less the taxes)
$breakdown->totalWithTax()->minorUnits;    // 1000, the price, always
```

- La base avant taxes est le prix divisé par un plus la somme des taux, arrondie à la moitié en s'éloignant de zéro :
  8,70 ici.
- Chaque taxe est calculée sur cette base et arrondie sur sa propre ligne, comme le fait `calculate()`.
- La base de la ventilation est le prix moins ces taxes : un cent d'arrondi reste ainsi dans la base, et le prix payé
  ne change jamais. Elle vaut 8,69 ici. Ses lignes sont `reported()` sur cette base.
- Un prix négatif donne exactement les taxes de signe opposé, pour un remboursement.
- Un prix trop grand pour le calcul lève `UntaxableBase` : sa valeur absolue ne peut pas dépasser
  `intdiv(PHP_INT_MAX - parts, 1000000)` unités mineures, où `parts` vaut un million plus les parties par million de
  chaque taux.
