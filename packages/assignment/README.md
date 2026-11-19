# phil/assignment

**Assignment by ranked preferences — game tables, workshops, rooms, roles — in rounds of choices or by a global
optimisation, deterministic for a seed and explained placement by placement.**

phil/assignment places people where they asked to go. Each applicant — a person, or a group that stays together —
ranks the slots they want; each slot has a capacity and a minimum below which it does not run; the organiser may forbid
an applicant a slot, or force one into it. The library answers with a placement for each applicant, the round and rank
that gave it, the applicants left without a slot and what became of each of their choices, and the slots closed for
ending under their minimum.

Two families of assigners solve the same problem. `RankedRounds` serves the choices in an order a seed draws: round
after round — every first choice, then every second choice of those still waiting — or in one pass where each applicant
takes their best choice with room. `MinCostAssigner` looks at everyone at once: a minimum-cost flow places as many
people as can be placed and, among those placements, gives the lowest total rank cost. Both give the same result for
the same problem and seed, on every machine, and the seed and draw order come back with the result, so that anyone can
run the assignment again and check it.

It is for any PHP application that fills limited places from wishes: a convention seating players at game tables, a
school sending pupils to workshops, a retreat sharing rooms, a live-action game casting roles. It is a plain PHP
library without any dependency. Problems are bounded before any work and every run has a work budget, so that input
nobody bounded cannot hold a worker.

## Status

| Part | State |
|---|---|
| Slots with capacities and minimums, applicants alone or in groups, forbidden and forced pairs, limits | Implemented and tested |
| Rounds of ranked choices, same or alternating order; serial dictatorship | Implemented and tested |
| Global optimisation by minimum-cost flow, linear or squared rank costs, groups seated first | Implemented and tested |
| Slots closed under their minimum and their people placed again | Implemented and tested |
| Seeded, reproducible draw; results explained per placement; plain data for an audit log | Implemented and tested |
| Reading a problem from JSON or arrays, with a fuzz target | Implemented and tested |
| Keeping two people apart, or splitting a group across slots | Not planned: groups stay whole, constraints are applicant–slot pairs |
| Storage, forms and screens | Left to the application: the library takes a problem and returns a result |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Usage](doc/usage.md) | Building a problem, choosing an assigner, reading the result, keeping it for audit. |
| [Algorithms](doc/algorithms.md) | Each mechanism, what it guarantees, how minimums close slots, complexity and budgets. |
| [Security](doc/security.md) | Limits, the work budget, identifiers, and reading untrusted input. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its benchmark and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/assignment
```

```php
use Phil\Assignment\Applicant;
use Phil\Assignment\Problem;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;

$problem = new Problem(
    [new Slot('table-1', capacity: 5, minimum: 3), new Slot('table-2', capacity: 4)],
    [
        Applicant::person('u-104', ['table-1', 'table-2']),
        Applicant::group('g-7', ['u-201', 'u-202'], ['table-2', 'table-1']),
    ],
);

$seed = Seed::random();                                   // keep $seed->value with the result
$result = new RankedRounds()->assign($problem, $seed);    // or new MinCostAssigner()

foreach ($result->placements as $placement) {
    printf("%s: %s, choice %d, round %d\n", $placement->applicant, $placement->slot, $placement->rank, $placement->round);
}
```

## Requirements

PHP 8.5 or later; nothing else.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
