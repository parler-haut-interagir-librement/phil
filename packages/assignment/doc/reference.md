# Reference

Every public type of the package, with its role. The classes under `Internal\` are not part of the public API.

## The problem

| Type | Role |
|---|---|
| `Problem` | The slots, the applicants, the forbidden and forced pairs, checked whole at construction and kept in identifier order; `slot()`, `applicant()`, `slotIds()`, `applicantIds()`, `isForbidden()`, `forcedSlot()`. |
| `Slot` | A place with its capacity and its minimum; `Slot::MAX_CAPACITY` bounds the capacity. |
| `Applicant` | A person or a group placed as one, with its members and its ranked choices; `person()`, `group()`, `size()`, `rankOf()`. |
| `Pair` | An applicant and a slot, forbidden or forced. |
| `Limits` | The sizes a problem may reach: slots, applicants, choices per applicant, members per group. |
| `ProblemReader` | Reads a problem from JSON or arrays, checking every field and bound; `ProblemReader::MAX_JSON_BYTES`. |

## The assigners

| Type | Role |
|---|---|
| `Contract\Assigner` | Solves a problem for a seed; the same input gives the same result. |
| `RankedRounds` | Ranked choices in draw order, by rounds or by serial dictatorship, with closings under minimums. |
| `Mechanism` | `Rounds` or `SerialDictatorship`. |
| `RoundOrder` | `Same` or `Alternating`: the order of the waiting applicants in each round. |
| `MinCostAssigner` | The global optimisation: groups first, then a minimum-cost maximum flow; `MinCostAssigner::MAX_COST`. |
| `Contract\RankCost` | What a rank costs the optimisation. |
| `Cost\LinearRankCost` | 0, 1, 2, 3…: the sum of ranks. |
| `Cost\SquaredRankCost` | 0, 1, 4, 9…: bad placements weigh more. |
| `Seed` | The seed of the draw; `fromString()`, `random()`, `shuffle()`. |
| `WorkBudget` | The most elementary steps one assignment may take. |

## The result

| Type | Role |
|---|---|
| `Assignment` | The placements, the unplaced applicants, the closed slots, the seed, the draw order, the method and the cost; `placementOf()`, `placementOfMember()`, `isUnplaced()`, `membersOf()`, `occupancy()`, `rankCounts()`, `toArray()`. |
| `Placement` | Where an applicant was placed: slot, `PlacedBy`, rank and round. |
| `PlacedBy` | `Forced`, `Round`, `Group` or `Optimisation`. |
| `Unplaced` | An applicant left without a slot, with a `RefusedChoice` for each of their choices. |
| `RefusedChoice` | A choice and its `ChoiceOutcome`. |
| `ChoiceOutcome` | `Full`, `Forbidden` or `Closed`. |

## The exceptions

| Type | Role |
|---|---|
| `Exception\AssignmentException` | Every exception of the package, to catch them together. |
| `Exception\InvalidProblem` | A problem, a value or an input that cannot be solved as given. |
| `Exception\BudgetExceeded` | An assignment that needed more work than its budget. |
