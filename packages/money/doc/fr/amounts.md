<!-- phil-dev source-sha256=b7b6d1404337fd08100627f34aa8031f8ec9baff5dc38dd71cb5e135e9af805d -->
# Montants

`Phil\Money\Money` est un montant dans la plus petite unité de sa devise (les cents pour un dollar), accompagné de cette
devise.

## Créer un montant

| Constructeur | Contrat |
|---|---|
| `static of(int $minorUnits, Currency $currency): self` | Tout entier sauf `PHP_INT_MIN` |
| `static zero(Currency $currency): self` | Zéro dans cette devise |
| `static ofDecimal(string $amount, Currency $currency): self` | Un décimal écrit par une personne ou un fichier |
| `static sum(self $first, self ...$others): self` | La somme d'un ou de plusieurs montants, tous dans une même devise |

`ofDecimal()` lit des chiffres, un `-` initial facultatif et un `.` facultatif suivi de chiffres :

```php
$cad = Currency::of('CAD');

Money::ofDecimal('12.34', $cad);   // 1234
Money::ofDecimal('12.5', $cad);    // 1250
Money::ofDecimal('12.3400', $cad); // 1234: trailing zeros are not precision
Money::ofDecimal('12.345', $cad);  // InvalidAmount: finer than the currency counts
Money::ofDecimal('12,34', $cad);   // InvalidAmount: not a decimal
Money::ofDecimal('1200', Currency::of('JPY')); // 1200
```

Un décimal plus fin que la devise est **refusé, jamais arrondi** : l'arrondi est une décision d'affaires (un prix, une
taxe, un intérêt) qui appartient au code qui la prend. Les séparateurs de milliers, la virgule décimale d'une locale et
les espaces relèvent de l'analyseur ; le type de formulaire du bundle les lit dans la locale de la requête.

## Lire un montant

| Membre | Contrat |
|---|---|
| `public int $minorUnits`, `public Currency $currency` | La valeur |
| `toDecimal(): string` | `12.34`, `-0.05`, `0.00`, `1200` pour le yen : toujours toutes les décimales de la devise |
| `isZero()`, `isPositive()`, `isNegative()` | Le signe |

`toDecimal()` est un format machine, l'inverse de `ofDecimal()`. Afficher un montant à une personne (symbole,
groupement, séparateur décimal de la locale) relève de `NumberFormatter`, ou du filtre `format_money` du bundle.

## Arithmétique et comparaison

| Membre | Contrat |
|---|---|
| `add(self $other)`, `subtract(self $other)` | Même devise seulement |
| `negate()`, `abs()` | Toujours possibles : l'intervalle est symétrique |
| `multiply(int $factor)` | Une quantité, un nombre de mois ; une fraction relève de `allocate()` |
| `compare(self $other): int` | `-1`, `0` ou `1` ; même devise seulement |
| `isGreaterThan(self $other)`, `isLessThan(self $other)` | Même devise seulement |
| `equals(self $other): bool` | Même devise et même montant ; `false`, jamais une exception, entre devises |

Chaque opération retourne un nouveau montant : `Money` est immuable.

## Choix de conception

- **Des entiers en unités mineures.** Un nombre à virgule flottante ne peut pas contenir exactement la plupart des
  montants décimaux, et ses erreurs s'additionnent d'une ligne à l'autre.
- **La devise fait partie de la valeur.** Des montants dans deux devises ne sont jamais additionnés, soustraits ni
  comparés : l'opération lance `CurrencyMismatch`. **Aucune conversion n'a lieu ici, jamais.**
- **Un montant négatif est légitime.** Une note de crédit annule une ligne de facture.
- **L'intervalle est celui de l'entier natif, symétrique.** PHP change en silence un dépassement d'entier en nombre à
  virgule flottante ; `Money` le refuse avec `InvalidAmount`. `PHP_INT_MIN` est aussi refusé, puisqu'il n'a pas de
  contrepartie positive : tout montant peut être rendu négatif. Sur PHP 64 bits, cela dépasse 92 billions de dollars.
