# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Price the ranks differently in the optimisation | Implement `Contract\RankCost` — a cost of 0 to `MinCostAssigner::MAX_COST` for each rank, not falling as the rank grows — and pass it to `MinCostAssigner` |
| Report a cost with the rounds | Pass the same `RankCost` to `RankedRounds`, which prices its result with it for comparison |
| Write another assigner | Implement `Contract\Assigner`: take a `Problem` and a `Seed`, return an `Assignment`, give the same result for the same input |
| Choose how the rounds run | Pass a `Mechanism` and a `RoundOrder` to `RankedRounds` |
| Bound the input or the work | Pass `Limits` to `Problem` or `ProblemReader`, and a `WorkBudget` to an assigner |
| Read problems from another format | Build a `Problem` from your data, or hand `ProblemReader::fromArray()` the arrays your format decodes to |
| Explain a placement to a person | Read `Placement::$placedBy`, `$rank` and `$round`, and the `RefusedChoice` list of an `Unplaced`, and write the sentences in your application's language |

The classes under `Internal\` are not extension points: they may change in any version.
