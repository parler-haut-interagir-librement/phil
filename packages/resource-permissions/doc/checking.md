# Checking and listing

`RelationChecker` implements `Contract\RelationCheck` from a `Model`, a `TupleStore` and a `Budget`.

## `check()` and `explain()`

```php
$checker->check($member, 'viewer', $doc);   // bool
$checker->explain($member, 'viewer', $doc); // ?Explanation
```

A check walks the rewrite of the relation:

- **direct**: one read of the store matches the subject itself, and every object of its kind when the subject is one
  object; then each subject set written on the relation — `group:staff#member` — is checked in turn;
- **computed**: the other relation, on the same object;
- **from related**: each object the followed relation points at, and the relation read on it;
- **union** answers yes at the first operand that does; **intersection** needs every operand; **exclusion** needs the
  base and not the subtracted side.

The `Explanation` holds the tuples followed, from the object to the subject: `doc:1#parent@folder:sub`,
`folder:sub#parent@folder:root`, `folder:root#editor@group:g#member`, `group:g#member@member:ann`. An intersection
gives the tuples of each of its operands, one after the other. `lines()` writes them as text, to show *why* a member
sees an item.

A subject may be a set: `check(Subject::set($group, 'member'), 'viewer', $doc)` says whether the members of the group,
as a whole, are given the relation.

## `objects()`

```php
$list = $checker->objects($member, 'viewer', 'doc', limit: 100);
$list->objects;  // list<ResourceReference>, ordered by identifier
$list->ids();    // list<string>, for WHERE id IN (:ids)
$list->complete; // false when cut
```

A listing walks the rewrites backwards: from the subject to the groups it belongs to, to the folders those groups
may view, down to the documents under them, each step one indexed read of the store. A group inside a group, or any
relation that leads back to itself, is gathered in rounds until a round adds nothing. Every candidate is then checked
with `check()`, so the list never holds an object a check would refuse: an exclusion or an intersection filters its
first operand's candidates.

The limit is 1 to 1,000. The list is incomplete when more objects check than the limit, or when the candidates
reach the budget's `maxCandidates`; an application that pages through more uses a query of its own on the tuples, or a
narrower relation.

## The budget

```php
new Budget(maxDepth: 32, maxReads: 500, maxFanOut: 200, maxCandidates: 1000);
```

| Limit | Default | Bounds |
|---|---|---|
| `maxDepth` | 32 | Relations followed one inside the other. |
| `maxReads` | 500 | Reads of the store by one check, or by the gathering of one listing. |
| `maxFanOut` | 200 | Subject sets, or related objects, behind one relation. The subjects that are one object do not count: they are matched by one read. |
| `maxCandidates` | 1,000 | Objects a listing looks at. |

Each limit is 1 to 100,000. A check or a listing past a limit throws `TraversalBudgetExceeded`: the answer is unknown,
and the caller refuses access. `phil/access-bundle`'s voters deny and log it.

## Cycles

A step already being walked — a group that contains itself through another, a folder that is its own ancestor — is a
cycle: that path answers no, and the walk goes on with the others. A no found while a cycle was cut short is never
kept for later in the same call, since another path may say yes. An exclusion whose subtracted side met a cycle answers
no: a no that may come from the cut never grants access.

## Expiry

A tuple with an expiry stops counting at that second, by the store's clock: no read returns it, so every check and
listing ignores it at once, without a job. `TupleStore::purgeExpired()` deletes the expired rows later.

## Performance

A check costs one read per step it walks: the depth of the tree and of the groups, never the number of tuples. A
listing costs one read per holder of the relation it gathers through, then a check per candidate.
`tests/Benchmark/RelationCheckerBench.php` measures both on a chain of 4 to 16 folders with nested groups.
