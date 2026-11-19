<!-- phil-dev source-sha256=3cfc7c21880c9103c67b15c083ca6777ef323ca0178e484db95431a565edfe7f -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Établir un autre prix des rangs dans l'optimisation | Implémentez `Contract\RankCost` — un coût de 0 à `MinCostAssigner::MAX_COST` pour chaque rang, qui ne baisse pas quand le rang monte — et passez-la à `MinCostAssigner` |
| Rapporter un coût avec les rondes | Passez le même `RankCost` à `RankedRounds`, qui s'en sert pour établir le coût de son résultat, à des fins de comparaison |
| Écrire un autre assigneur | Implémentez `Contract\Assigner` : recevoir un `Problem` et un `Seed`, renvoyer un `Assignment`, donner le même résultat pour la même entrée |
| Choisir le déroulement des rondes | Passez un `Mechanism` et un `RoundOrder` à `RankedRounds` |
| Borner l'entrée ou le travail | Passez `Limits` à `Problem` ou à `ProblemReader`, et un `WorkBudget` à un assigneur |
| Lire des problèmes dans un autre format | Construisez un `Problem` à partir de vos données, ou donnez à `ProblemReader::fromArray()` les tableaux auxquels votre format se décode |
| Expliquer un placement à une personne | Lisez `Placement::$placedBy`, `$rank` et `$round`, et la liste de `RefusedChoice` d'un `Unplaced`, et écrivez les phrases dans la langue de votre application |

Les classes sous `Internal\` ne sont pas des points d'extension : elles peuvent changer à toute version.
