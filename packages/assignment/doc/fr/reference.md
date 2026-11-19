<!-- phil-dev source-sha256=cb3c30393a39e3c05f07d4cf5c90df48a28611ea84471fb0811152ac2fb1d7e4 -->
# Référence

Chaque type public du paquet, avec son rôle. Les classes sous `Internal\` ne font pas partie de l'API publique.

## Le problème

| Type | Rôle |
|---|---|
| `Problem` | Les créneaux, les candidats, les paires interdites et forcées, vérifiés en bloc à la construction et gardés dans l'ordre des identifiants ; `slot()`, `applicant()`, `slotIds()`, `applicantIds()`, `isForbidden()`, `forcedSlot()`. |
| `Slot` | Une place avec sa capacité et son minimum ; `Slot::MAX_CAPACITY` borne la capacité. |
| `Applicant` | Une personne ou un groupe placé d'un seul tenant, avec ses membres et ses choix classés ; `person()`, `group()`, `size()`, `rankOf()`. |
| `Pair` | Un candidat et un créneau, interdits ou forcés. |
| `Limits` | Les tailles qu'un problème peut atteindre : créneaux, candidats, choix par candidat, membres par groupe. |
| `ProblemReader` | Lit un problème depuis du JSON ou des tableaux, en vérifiant chaque champ et chaque borne ; `ProblemReader::MAX_JSON_BYTES`. |

## Les assigneurs

| Type | Rôle |
|---|---|
| `Contract\Assigner` | Résout un problème pour un germe ; la même entrée donne le même résultat. |
| `RankedRounds` | Choix classés dans l'ordre du tirage, par rondes ou par dictature en série, avec fermeture sous les minimums. |
| `Mechanism` | `Rounds` ou `SerialDictatorship`. |
| `RoundOrder` | `Same` ou `Alternating` : l'ordre des candidats en attente à chaque ronde. |
| `MinCostAssigner` | L'optimisation globale : les groupes d'abord, puis un flot maximal de coût minimal ; `MinCostAssigner::MAX_COST`. |
| `Contract\RankCost` | Ce que coûte un rang à l'optimisation. |
| `Cost\LinearRankCost` | 0, 1, 2, 3… : la somme des rangs. |
| `Cost\SquaredRankCost` | 0, 1, 4, 9… : les mauvais placements pèsent plus. |
| `Seed` | Le germe du tirage ; `fromString()`, `random()`, `shuffle()`. |
| `WorkBudget` | Le plus grand nombre d'étapes élémentaires qu'une affectation peut prendre. |

## Le résultat

| Type | Rôle |
|---|---|
| `Assignment` | Les placements, les candidats non placés, les créneaux fermés, le germe, l'ordre du tirage, la méthode et le coût ; `placementOf()`, `placementOfMember()`, `isUnplaced()`, `membersOf()`, `occupancy()`, `rankCounts()`, `toArray()`. |
| `Placement` | Où un candidat a été placé : créneau, `PlacedBy`, rang et ronde. |
| `PlacedBy` | `Forced`, `Round`, `Group` ou `Optimisation`. |
| `Unplaced` | Un candidat resté sans créneau, avec un `RefusedChoice` pour chacun de ses choix. |
| `RefusedChoice` | Un choix et son `ChoiceOutcome`. |
| `ChoiceOutcome` | `Full`, `Forbidden` ou `Closed`. |

## Les exceptions

| Type | Rôle |
|---|---|
| `Exception\AssignmentException` | Toutes les exceptions du paquet, pour les attraper ensemble. |
| `Exception\InvalidProblem` | Un problème, une valeur ou une entrée qu'on ne peut pas résoudre telle quelle. |
| `Exception\BudgetExceeded` | Une affectation qui a demandé plus de travail que son budget. |
