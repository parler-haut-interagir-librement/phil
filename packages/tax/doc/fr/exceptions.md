<!-- phil-dev source-sha256=c6994d8827b59c47f279692e9d6dfb0fad5fe6b100599ee40aee58af20c33551 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Tax\Exception\TaxException`, qui étend `Throwable` : une
application les traite à un seul endroit. Chacune est une erreur de l'appelant ou de son entrée, jamais une
défaillance de la bibliothèque. Quand deux devises se rencontrent, l'exception garde le type de `phil/money`,
`Phil\Money\Exception\CurrencyMismatch`, qui implémente `MoneyException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidTaxRate` | `InvalidArgumentException` | `TaxRate::of()` ou `ofPercent()` reçoit un code, une part ou un pourcentage qu'un taux ne peut pas contenir |
| `InvalidBreakdown` | `DomainException` | `TaxBreakdown::of()` reçoit une ligne sur une autre base, ou un code de taux deux fois |
| `UntaxableBase` | `DomainException` | `TaxRate::taxOn()` (et donc `calculate()`) reçoit une base dont la taxe ne tient pas dans un entier natif, ou `MultiRateTaxCalculator::extract()` reçoit un prix supérieur à `intdiv(PHP_INT_MAX - parts, 1000000)` unités mineures, `parts` valant un million plus les parties par million de chaque taux |

| Constructeur nommé | Message |
|---|---|
| `InvalidTaxRate::code(string $code)` | `"gst" is not a tax rate code: up to 32 uppercase letters, digits, "_" or "-", starting with a letter.` |
| `InvalidTaxRate::outOfRange(int $partsPerMillion)` | `A tax rate lies between 0 and 1000000 parts per million; 1000001 does not.` |
| `InvalidTaxRate::notAPercent(string $percent)` | `"9,975" is not a percentage a tax rate holds: digits with up to 4 decimals after ".", from 0 to 100, are expected.` |
| `InvalidBreakdown::anotherBase(string $code)` | `The QST line is on another base: every rate of a breakdown applies to the same base.` |
| `InvalidBreakdown::duplicateRate(string $code)` | `The GST rate appears twice in one breakdown.` |
| `UntaxableBase::tooLarge(int $largest)` | `The taxable base is too large to be taxed in a native integer: 9223372036853 minor units at most.` |

Les messages sont écrits pour les développeurs et les journaux, pas pour les gens qui achètent.
