<!-- phil-dev source-sha256=ea352e6bfa11a924de86479593fcf8cd40723052455e1690f87665467f37854b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/assignment` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Problem`, `Slot`, `Applicant` et `Pair` : des places avec une capacité et un minimum, des candidats seuls ou en
  groupes qui restent ensemble avec leurs choix classés, des paires candidat–place interdites et forcées, vérifiés en
  entier à la construction et gardés dans l'ordre des identifiants ; les identifiants qui ressemblent à une adresse
  courriel sont refusés.
- Des `Limits` sur la taille d'un problème, et un `WorkBudget` sur le travail d'une affectation, avec `BudgetExceeded`
  au-delà.
- `Seed` : un tirage reproductible sur le moteur `Xoshiro256StarStar` de PHP, à partir d'un texte ou de la source sûre
  du système.
- `RankedRounds` : des tours de choix classés dans le même ordre ou dans un ordre alterné, et la dictature sérielle.
- `MinCostAssigner` : les groupes placés d'abord, puis un flot maximal de coût minimal résolu par la méthode
  primale-duale, avec `LinearRankCost` et `SquaredRankCost` derrière `Contract\RankCost`.
- Les places sous leur minimum fermées une à la fois et le problème résolu de nouveau, pour chaque méthode
  d'affectation.
- `Assignment`, `Placement`, `Unplaced` et `RefusedChoice` : chaque placement avec son tour et son rang, chaque choix
  refusé avec sa raison, les places fermées, le germe et l'ordre du tirage, et `toArray()` pour un journal d'audit.
- `ProblemReader` : un problème lu de JSON ou de tableaux, chaque champ et chaque borne vérifiés.
- Une cible de fuzzing sur le lecteur et les méthodes d'affectation, et un banc d'essai de mille personnes et cent
  places.
- La documentation dans `doc/` : installation, usage, algorithmes et leurs garanties, sécurité, points d'extension,
  tests et référence.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
