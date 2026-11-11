# Criteria

A criterion is a name the application defines — lowercase ASCII segments of at most 64 bytes — and a value of one of
three types:

| Type | Built by | Holds | Meets a wanted criterion of the same name and type when |
|---|---|---|---|
| Choice | `Criterion::choice($name, $choices)` | 1 to 32 distinct values of up to 64 ASCII letters, digits, `.`, `_`, `:` or `-`, in the order given | they share at least one value |
| Range | `Criterion::range($name, $min, $max)` | two integers, both ends included, `$min` not above `$max` | they share at least one integer |
| Flag | `Criterion::flag($name, $flag)` | yes or no | they are equal |

A choice's values are keys, not labels: the application translates them, and the same key means the same thing in
every language. A range holds whole numbers: a salary band in whole units, a number of players, an age bracket. The
fields a type does not use are empty: a choice has no ends, a range no choices.

```php
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;

$listing = Criteria::of([
    Criterion::choice('day', ['sat', 'sun']),
    Criterion::range('players', 3, 5),
    Criterion::flag('beginners', true),
]);

$search = Criteria::of([Criterion::choice('day', ['sun']), Criterion::range('players', 4, 8)]);

$listing->meets($search);   // true: Sunday is shared, and 4 or 5 players fit both
```

`Criteria::of()` keeps at most 32 criteria, one per name. A listing's criteria meet a search's when every criterion
the search wants meets the listing's criterion of the same name: a listing that does not state a criterion does not
meet a search that wants it, and a search that wants nothing meets every listing.

`meets()` is the rule, not the search: an application with many listings filters them in its store or its search
engine first, then applies `meets()` to what it reads, or indexes the same rule. Its cost is bounded by the 32
criteria and 32 values of each side.
