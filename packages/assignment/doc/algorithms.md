# Algorithms

Every assigner starts the same way: it draws the order of the applicants from the seed, places the forced applicants,
then solves for the others. Whatever the mechanism, a slot never holds more than its capacity, an applicant is placed at
most once and never in a forbidden or closed slot, every forced applicant is in its slot, and a group is placed whole
or not at all.

## The draw

The applicants are taken in identifier order and shuffled by PHP's `Random\Randomizer` on the `Xoshiro256StarStar`
engine, seeded with the SHA-256 of the seed's text. PHP fixes the sequence of that engine, so the same seed gives the
same order on every machine and every PHP version. The draw order comes back with the result.

## Rounds of ranked choices

`RankedRounds` with `Mechanism::Rounds`: round 1 offers every waiting applicant their first choice, in draw order; an
applicant whose choice has room is placed, the others wait. Round 2 offers the waiting applicants their second choice,
and so on, until no one waits or no one has a choice left for the round. A choice that was full when its round came is
lost, even if it was the applicant's first.

- Everyone's first choice is considered before anyone's second: the number of first choices granted is as high as the
  draw allows.
- With `RoundOrder::Alternating`, the even rounds take the waiting applicants in reverse draw order: the applicant
  served last in round 1 is served first in round 2.
- An applicant may gain by ranking first a slot less popular than the one they want most, since a contested first
  choice may cost them their second. Organisers who announce rounds usually accept this; serial dictatorship does not
  have it.

Each placement carries its round and its rank, which are equal in this mechanism.

## Serial dictatorship

`RankedRounds` with `Mechanism::SerialDictatorship`: one pass in draw order, each applicant taking their best choice
that still has room. Each placement carries round 1 and the rank obtained.

- No applicant gains by misstating their ranking: the choices above the one they got were full when their turn came.
- The result is Pareto efficient for the draw: no two applicants can swap and both be better off.
- The draw matters more than in the rounds: the first drawn always get their first choice, the last drawn get what is
  left.

## Global optimisation

`MinCostAssigner` solves for everyone at once:

1. Groups, in draw order, each take their best choice with room for all of them, placed as `PlacedBy::Group`.
2. The people on their own form a flow network: one edge from a source to each person, one edge from each person to
   each slot they ranked and may enter, priced by the rank cost of that choice, and one edge from each slot to a sink
   with the seats left. A minimum-cost maximum flow on this network is the assignment.
3. The flow is solved by the primal-dual method: Dijkstra's algorithm on costs reduced by node potentials prices the
   network, then depth-first searches push flow along every path of zero reduced cost, and the network is priced
   again until the sink cannot be reached. A warm start first seats each person, in draw order, at a choice of the
   cheapest cost while one has room.

The result places as many people on their own as any assignment of the seats left could, and among assignments that
place that many, its total rank cost is the lowest. Among assignments of equal cost, the draw order decides, through
the order of the network's nodes. The test suite checks both properties against an exhaustive search on 150 random
problems, for both rank costs.

The rank cost says what the optimum means:

| Cost | Rank 1, 2, 3, 4 | The optimisation |
|---|---|---|
| `LinearRankCost` | 0, 1, 2, 3 | makes the sum of ranks as small as possible; may give one person their fourth choice to give three others their first |
| `SquaredRankCost` | 0, 1, 4, 9 | weighs a bad placement more than several small ones, and spreads the disappointment |

Groups are seated first because a group must stay whole: a flow may split the seats of a group across slots, and
seating whole groups optimally is a packing problem with no efficient exact method. Seating them first, in draw order,
keeps the result exact for everyone else and explains each group's placement.

## Minimums

After a solve, an open slot that holds some people but fewer than its minimum is closed: the one with the fewest people
first, and between equals the one with the first identifier. The whole problem is then solved again without it, from
the same draw, so that its people go to their next choices. This repeats until no open slot runs under its minimum.

- An empty slot is not running and is never closed.
- A slot that holds a forced applicant is never closed: the organiser forced it.
- Closing a slot can push another under its minimum; the closings go on, one at a time, so that each closing is
  explained by the solve before it.

The slots closed come back in `closedSlots`, in the order they were closed, and an unplaced applicant's choice in a
closed slot reads `ChoiceOutcome::Closed`.

## Complexity and budgets

| Assigner | One solve | With minimums |
|---|---|---|
| `RankedRounds` | O(A · C) for A applicants ranking up to C slots | up to S + 1 solves for S slots |
| `MinCostAssigner` | O(F · E log V) in the worst case, F the people on their own, E their choices plus F plus S, V the applicants plus S; far fewer pricings when ranks are few, since one pricing serves every path of the same cost | up to S + 1 solves |

Measured on a laptop with PHP 8.5, a thousand people ranking five of a hundred slots of ten seats: a few milliseconds
for the rounds and for serial dictatorship, and about a tenth of a second more when minimums close slots; between 0.2
and 0.5 s for the optimisation, and one to two seconds when minimums close a few slots and the problem is solved
again.

Two bounds keep a run within reach of a request:

- `Limits` refuses a problem past 5 000 slots, 20 000 applicants, 20 choices per applicant or 50 members per group
  before any work. An application lowers them for input it does not trust.
- `WorkBudget` counts the elementary steps — a choice tried, an edge relaxed, a step of a search — over the whole run,
  closings included, and throws `BudgetExceeded` past 50 million by default. A problem within the limits but hostile in
  shape stops there instead of holding a worker. An application that assigns offline raises it.
