# Security

An assignment often runs on input people typed — their choices, their group, a file an organiser uploaded — inside a
web request or a worker shared by many requests. The library keeps that input from costing more than it should, and
keeps the people it places pseudonymous.

## Bounded before any work

`Problem` checks its size against `Limits` before it builds anything: 5 000 slots, 20 000 applicants, 20 choices per
applicant and 50 members per group by default. `ProblemReader` checks the same bounds on each list before it reads its
items, refuses a JSON text over 16 MiB unread, and decodes JSON no deeper than its shape needs. A problem that passes
the limits but would need too much work — many closings of slots under their minimum, a flow network shaped to be
slow — stops at its `WorkBudget` with `BudgetExceeded`.

For input from the public, lower both:

```php
use Phil\Assignment\Limits;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\ProblemReader;
use Phil\Assignment\WorkBudget;

$reader = new ProblemReader(new Limits(slots: 200, applicants: 2_000, choices: 10, members: 8));
$assigner = new MinCostAssigner(budget: new WorkBudget(5_000_000));
```

## Refused, never half-read

Everything the reader and the problem refuse is an `InvalidProblem`, with a message that names the field: no PHP
warning, no type error, no partial problem. The fuzz target in `tests/Fuzz/problem.php` feeds the reader and the four
assigners text nobody wrote, and checks that nothing else comes out and that every result keeps its guarantees.

## Pseudonymous identifiers

Slots, applicants and members are named by identifiers of 1 to 255 bytes without control characters. An identifier
that looks like an e-mail address is refused: an address changes and names a person, where the application's own
stable identifier — an account number, a registration code — does neither. The result and its audit log then hold
identifiers only.

## Reproducible and checkable

The draw runs on a seeded engine whose sequence PHP fixes, and the seed comes back with the result. An organiser who
announces the seed before the draw, or who publishes it with the result, lets any participant run the same assignment
and check that nobody's placement was changed by hand. `Seed::random()` draws a seed from the system's secure source
when nobody should choose it.

## Shared across requests

The assigners and the reader keep no state between calls: the work counter and every intermediate structure are made
for each run. They are safe to share across the requests of a long-running worker.
