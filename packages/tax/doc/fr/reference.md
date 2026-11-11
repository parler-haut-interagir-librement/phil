<!-- phil-dev source-sha256=7b32ca54bb259659483e2091b16ca61d5a25a16bd4f66b02db2cab359a0e4def -->
# Référence

Chaque type de la bibliothèque, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Tax\TaxRate` | `final readonly class` | Un code et une part en parties par million ; les pourcentages ; la taxe sur une base |
| `Phil\Tax\TaxLine` | `final readonly class` | Un taux, sa base, son montant ; calculée ou fournie |
| `Phil\Tax\TaxBreakdown` | `final readonly class` | Des lignes sur une même base ; ligne par code ; totaux ; inversion |
| `Phil\Tax\MultiRateTaxCalculator` | `final readonly class` | Chaque taux sur la même base, une ligne chacun (`calculate()`) ; les taxes incluses dans un prix, et la base qu'elles laissent (`extract()`) |
| `Phil\Tax\Exception\TaxException` | interface, étend `Throwable` | Chaque exception de la bibliothèque |
| `Phil\Tax\Exception\InvalidTaxRate` | `final class`, `InvalidArgumentException` | Un code, une part ou un pourcentage refusé |
| `Phil\Tax\Exception\InvalidBreakdown` | `final class`, `DomainException` | Une ligne sur une autre base, ou un code deux fois |
| `Phil\Tax\Exception\UntaxableBase` | `final class`, `DomainException` | Une base trop grande pour être taxée dans un entier natif |

Voir [rates.md](rates.md), [breakdowns.md](breakdowns.md), [choosing-rates.md](choosing-rates.md) et
[exceptions.md](exceptions.md).
