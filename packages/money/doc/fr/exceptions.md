<!-- phil-dev source-sha256=45046ddd6ec47ca9c1e516da9d77f26191bb83699217b97575ef69d6ca26d7f6 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Money\Exception\MoneyException`, qui étend `Throwable` : une
application les associe en un seul endroit. Chacune est une erreur de l'appelant ou de son entrée, jamais une
défaillance de la bibliothèque.

| Exception | Étend | Lancée quand |
|---|---|---|
| `UnknownCurrency` | `InvalidArgumentException` | `Currency::of()` reçoit un code que les données CLDR ne connaissent pas : `"XYZ" is not a known ISO 4217 currency code.` |
| `InvalidAmount` | `InvalidArgumentException` | Un décimal qui n'en est pas un, ou plus fin que la devise ; un montant hors de l'entier natif ; une répartition refusée |
| `CurrencyMismatch` | `DomainException` | Deux devises se rencontrent dans `add()`, `subtract()`, `compare()`, `isGreaterThan()`, `isLessThan()` ou `sum()` : `An amount in CAD cannot be combined with an amount in USD.` |

Les messages de `InvalidAmount` :

| Constructeur nommé | Message |
|---|---|
| `notADecimal(string $amount)` | `"12,34" is not a decimal amount: digits, with an optional "-" and "." are expected.` |
| `tooPrecise(string $amount, string $currency, int $fractionDigits)` | `"12.345" has more decimals than CAD counts (2).` |
| `outOfRange()` | `The amount leaves the range of a native integer.` |
| `because(string $reason)` | La raison donnée : les refus de `allocate()` et de `split()` |

Les messages sont écrits pour les développeurs et les journaux. Une personne qui saisit un montant voit le message
propre au formulaire, que le bundle traduit.
