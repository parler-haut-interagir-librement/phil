# Rules

## The tree

| Node | Holds when |
|---|---|
| `All(...$conditions)` | every condition holds; evaluated in order, it stops at the first that fails |
| `Any(...$conditions)` | at least one condition holds; evaluated in order, it stops at the first that holds |
| `Not($condition)` | its condition fails |
| `Leaf($type, $operands)` | the condition type named `$type` holds for its operands |

`All` and `Any` hold at least one condition: an empty one is refused rather than given a meaning by convention. The
library evaluates these four classes only.

## Facts

A rule never reads anything by itself: the caller passes a scope, a `FactSource`, and each condition reads the facts it
needs by name. `Facts::of()` takes values, or closures computed the first time a condition reads them:

```php
use Phil\Rules\Fact\Facts;

$facts = Facts::of([
    'country' => $address->country,
    'orders' => static fn (): int => $orders->countFor($customer), // computed only if a condition reads it
]);
```

A scope lives for one evaluation, or for the evaluations of one subject. An application may implement `FactSource`
itself, for instance over an entity, as long as it holds nothing from one request to the next.

## The built-in types

`ConditionTypes::builtIn()` registers six generic comparisons. Each reads the fact named by its `fact` operand and fails
with the reason `fact.missing` when the scope does not hold it.

| Type | Operands | Holds when |
|---|---|---|
| `fact.equals` | `fact`, `value` (scalar) | the fact is the value; numbers compare by value, `2` and `2.0` alike, anything else must be identical |
| `fact.in` | `fact`, `values` (scalars) | the fact is one of the values |
| `fact.contains` | `fact`, `value` (scalar) | the fact is a list that holds the value |
| `fact.compare` | `fact`, `operator` (`lt`, `lte`, `gt`, `gte`), `value` (number) | the fact is a number in that relation to the value |
| `fact.present` | `fact` | the scope holds the fact and it is not null |
| `date.compare` | `fact`, `operator` (`before`, `after`, `on-or-before`, `on-or-after`), `date` (`YYYY-MM-DD`) | the calendar date of the fact, in its own time zone, falls in that relation to the date |

Dates are compared as `YYYY-MM-DD` texts, never as objects. A fact of the wrong kind fails with the reason
`fact.type`, and the details name the kind expected.

Packages add their own types: an audience package declares `ticket.holder`, a shop `cart.total`. See
[Extension points](extension-points.md).

## The result

`Rule::evaluate()` returns a `Result`:

- `passed`: the answer;
- `evaluations`: how many leaves were evaluated;
- `trace`: a `Step` per condition, evaluated or skipped, with the JSON Pointer of its node (`/condition/all/1/not`),
  its kind, its outcome and, for a leaf, its type, its reason code and its details;
- `explanation()`: the leaves that decided. When the rule fails, these are the branch that failed: the failed child of
  a failed `All`, every child of a failed `Any`, the condition of a `Not` that held.

```php
foreach ($result->explanation() as $step) {
    printf("%s: %s %s\n", $step->path, $step->type, $step->reason); // /condition/all/0: fact.in fact.unlisted
}
```

The reason codes are stable texts an application translates. The details of the built-in types name facts and
operators, never the value a fact held, so that a trace may be logged; `Step::toArray()` gives the whole trace as
plain arrays.

## Limits

`Limits` bounds a rule; past a bound, `LimitExceeded` is thrown.

| Bound | Default | Checked |
|---|---|---|
| `maxBytes` | 65 536 | when the JSON form is read |
| `maxDepth` | 16 | when a rule is read or built |
| `maxNodes` | 256 | when a rule is read or built |
| `maxEvaluations` | 256 | at each evaluation, counting the leaves evaluated |

The tree of a rule is finite and immutable, so its evaluation ends; the bounds keep that end close. Each condition type
reads its operands and the facts it is given, and calls no rule in turn.
