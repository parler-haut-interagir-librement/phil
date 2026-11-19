# Usage

## Building a problem

A `Problem` holds everything an assignment reads, checked once, whole, at construction:

```php
use Phil\Assignment\Applicant;
use Phil\Assignment\Pair;
use Phil\Assignment\Problem;
use Phil\Assignment\Slot;

$problem = new Problem(
    slots: [
        new Slot('table-1', capacity: 5, minimum: 3),
        new Slot('table-2', capacity: 6, minimum: 4),
        new Slot('table-3', capacity: 4),
    ],
    applicants: [
        Applicant::person('gm-12', []),
        Applicant::person('u-104', ['table-1', 'table-3', 'table-2']),
        Applicant::group('g-7', ['u-201', 'u-202', 'u-203'], ['table-2', 'table-1']),
    ],
    forbidden: [new Pair('u-104', 'table-2')],
    forced: [new Pair('gm-12', 'table-1')],
);
```

- A `Slot` is a place: a game table, a workshop, a room, a role. Its capacity is the number of people it holds; its
  minimum, zero by default, is the number it needs to run.
- An `Applicant` is placed as one: `Applicant::person()` for one person, whose identifier is the applicant's;
  `Applicant::group()` for people who stay together, who take as many seats as they are, all in one slot. The choices
  are slot identifiers, the most wanted first; an applicant may rank none.
- A forbidden `Pair` keeps an applicant out of a slot, ranked or not. A forced `Pair` places an applicant in a slot
  before anyone else, ranked or not: a game master at their own table, a host in their room.

The problem refuses with `InvalidProblem` what it cannot solve as given: an identifier given twice, a person in two
applicants, a choice that names no slot, an applicant forced into two slots or into a slot also forbidden to it, forced
applicants that exceed a slot. The order of the lists does not matter: slots and applicants are kept in the order of
their identifiers, so that the same problem written in another order gives the same result.

## Choosing an assigner

Every assigner implements `Contract\Assigner` and takes the problem and a `Seed`:

```php
use Phil\Assignment\Cost\SquaredRankCost;
use Phil\Assignment\Mechanism;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\RoundOrder;
use Phil\Assignment\Seed;

$seed = Seed::fromString('spring-convention-2027');

$rounds = new RankedRounds()->assign($problem, $seed);
$fairer = new RankedRounds(Mechanism::Rounds, RoundOrder::Alternating)->assign($problem, $seed);
$serial = new RankedRounds(Mechanism::SerialDictatorship)->assign($problem, $seed);
$global = new MinCostAssigner(new SquaredRankCost())->assign($problem, $seed);
```

| Assigner | Use it when |
|---|---|
| `RankedRounds`, `Mechanism::Rounds` | The organiser announces rounds: everyone's first choice is served before anyone's second. The order of each round comes from the draw. |
| `RoundOrder::Alternating` | The same rounds, but the last served in a round is served first in the next, so that a bad draw costs less. |
| `Mechanism::SerialDictatorship` | Each applicant, in draw order, takes their best choice with room: simple to explain, and no one gains by ranking a slot lower than they want it. |
| `MinCostAssigner` | The organiser wants as many people placed as possible and the fewest bad placements overall, and accepts a result that is a global optimum rather than a sequence of turns. |

[Algorithms](algorithms.md) says what each one guarantees.

A seed is any text of 1 to 255 bytes: a name the organiser announces in advance, or `Seed::random()` when nobody should
choose it. Keep its value with the result: the same problem and seed give the same result again.

## Reading the result

`assign()` returns an `Assignment`:

```php
foreach ($result->placements as $placement) {
    // $placement->applicant, $placement->slot
    // $placement->placedBy: PlacedBy::Forced, Round, Group or Optimisation
    // $placement->rank: 1 for a first choice, null for a forced slot the applicant did not rank
    // $placement->round: the round of ranked choices that placed them, null outside the rounds
}

foreach ($result->unplaced as $unplaced) {
    foreach ($unplaced->choices as $choice) {
        // $choice->slot, $choice->outcome: ChoiceOutcome::Full, Forbidden or Closed
    }
}

$result->closedSlots;              // slots closed for ending under their minimum, in the order they were closed
$result->placementOf('g-7');       // by applicant
$result->placementOfMember('u-202'); // by person, alone or in a group
$result->membersOf('table-1');     // the people in a slot
$result->occupancy('table-1');     // the seats taken
$result->rankCounts();             // [1 => 41, 2 => 12, 3 => 3]: how many got each rank
$result->cost;                     // the total rank cost of the placements
```

Placements and unplaced applicants are lists in applicant identifier order. Each placement says enough to explain it
to the person — "your first choice, at round 1", "your second choice: your first was full at round 1" — and each
unplaced applicant says why each choice failed. The library gives the reasons as data; the application writes the
sentences in the reader's language.

## Keeping the result for audit

`toArray()` turns the result into plain data — the method and its settings, the seed, the draw order, every placement
and every refused choice — to keep beside the problem:

```php
$log = json_encode(['problem' => $input, 'result' => $result->toArray()], \JSON_THROW_ON_ERROR);
```

Anyone holding the problem and the seed can run the same assigner again and compare. `ProblemReader` reads the
problem back from the same JSON, or from arrays a form or a database gave:

```php
use Phil\Assignment\ProblemReader;

$problem = new ProblemReader()->fromJson($json);
```

The JSON has four lists, all optional:

```json
{
  "slots": [{"id": "table-1", "capacity": 5, "minimum": 3}],
  "applicants": [{"id": "u-104", "choices": ["table-1"]}, {"id": "g-7", "members": ["u-201", "u-202"]}],
  "forbidden": [{"applicant": "u-104", "slot": "table-2"}],
  "forced": [{"applicant": "g-7", "slot": "table-1"}]
}
```
