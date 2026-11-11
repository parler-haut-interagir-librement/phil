<!-- phil-dev source-sha256=272ff2658d750049fc70686fc6d1e4230b1b564faf42cf54f9b1d7e05294d9f0 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service ni aucun port, et ses classes sont finales. Elle
calcule et détaille ; le choix des taux qui s'appliquent revient à l'application, et parvient à la bibliothèque sous
forme d'arguments.

| Point | Comment |
|---|---|
| Quels taux s'appliquent | La liste donnée à `MultiRateTaxCalculator::calculate()` ou `extract()` : un résolveur propre à l'application, tiré de la configuration ou d'un moteur de taxation externe, la construit avec `TaxRate::of()` ou `TaxRate::ofPercent()` — voir [Choisir les taux](choosing-rates.md) |
| Des taxes déjà calculées par un moteur | `TaxLine::reported()` conserve un montant tel qu'il a été donné ; les lignes sont rassemblées par `TaxBreakdown::of()` : la ventilation vérifie qu'elles partagent une même base et les conserve sans les recalculer |
| Notes de crédit | `TaxBreakdown::reversed()` inverse exactement le signe de chaque ligne, quelle que soit leur provenance |
| Intercepter les erreurs de la bibliothèque | Chaque exception implémente `Phil\Tax\Exception\TaxException` : un seul `catch` couvre `InvalidTaxRate`, `InvalidBreakdown` et `UntaxableBase` |

L'arrondi — à la moitié en s'éloignant de zéro, par ligne — fait partie du contrat de la bibliothèque et ne se remplace
pas : un territoire qui arrondit autrement fournit ses lignes calculées ailleurs.
