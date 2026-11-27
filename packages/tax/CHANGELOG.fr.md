<!-- phil-dev source-sha256=45c1d73b83953afbe0f02ba131a708cfe531361bc25256c7f59815c033418511 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/tax` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `TaxRate` : un code et une part en parties par million ; `of()`, `ofPercent()`, `percent()`, `equals()`, et `taxOn()`,
  qui arrondit la moitié en s'éloignant de zéro en arithmétique entière.
- `TaxLine`, calculée ou déclarée, et `reversed()`.
- `TaxBreakdown` : des lignes sur une même base, chaque code de taux une seule fois ; `line()`, `totalTax()`,
  `totalWithTax()`, `reversed()`.
- `MultiRateTaxCalculator` : chaque taux sur la même base, une ligne chacun ; `extract()`, les taxes incluses dans un
  prix, et la base qu'elles laissent.
- `TaxException`, implémentée par `InvalidTaxRate`, `InvalidBreakdown` et `UntaxableBase`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
