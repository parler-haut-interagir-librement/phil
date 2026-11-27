<!-- phil-dev source-sha256=4a7988efe220f56f4cb14d3fa17bca8339d5f7bc8d16b800a40ecab96ab55f63 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/proposal-review` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/polyfill-php86` 1.43), and of each development requirement
  (`doctrine/dbal` 4.6).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Campaign`, `Schedule`, `PhaseWindow` et `Phase` : des phases datées et contiguës — soumission, évaluation, réponse
  des auteurs, délibération — gardées en UTC, lues sur une horloge PSR-20.
- `Criterion`, `Score` et `Scoring` : des critères pondérés sur leurs propres échelles, le score d'une évaluation de 0 à
  1, et son agrégation par moyenne ou par médiane.
- `ReviewPolicy` : l'évaluation à l'aveugle, le minimum et le maximum d'évaluations, `Aggregation`, la `Visibility` des
  évaluations des autres, les évaluateurs assignés seulement.
- `ReviewBoard` : les propositions déposées pendant la soumission ; les conflits d'intérêts par proposition ou par
  auteur ; l'assignation manuelle et équilibrée ; les évaluations vérifiées d'après les critères ; « enregistrer et
  suivant » ; les évaluations et les auteurs montrés selon la politique ; la fenêtre de réponse des auteurs ; le
  classement trié par `\SortDirection` ; les décisions consignées pendant la délibération et appliquées ensemble.
- `Pairwise\PairwiseVoting` : des paires tirées pour équilibrer l'exposition, jamais répétées pour un votant, les
  conflits laissés de côté ; les votes dans un journal numéroté en ajout seulement ; les scores par
  `Pairwise\BradleyTerry`, reproductibles à partir du journal.
- Des événements avec des identifiants seulement : `PhaseChanged`, `ReviewSubmitted`, `DecisionRecorded`,
  `DecisionsApplied`, `PairwiseVoteCast`.
- Quatre ports de stockage sous `Contract\`, avec des adaptateurs en mémoire et sur Doctrine DBAL : des tables
  construites à partir de leur propre schéma sous un préfixe vérifié, les identifiants comparés octet pour octet, les
  instants comme texte UTC, les votes numérotés de nouveau quand une autre requête a pris le numéro, les rangées que les
  stockages n'ont pas écrites refusées.
- Les identifiants qui ressemblent à une adresse courriel sont refusés.
- Un banc d'essai du classement, de l'ajustement de Bradley–Terry et du tirage des paires.
- La documentation dans `doc/` : installation, usage, vote par paires, algorithmes et leurs garanties, stockage,
  événements, sécurité, points d'extension, tests et référence.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
