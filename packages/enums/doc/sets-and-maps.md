# Sets and maps

Two structures keyed by the cases of one enum, which PHP arrays cannot be: `EnumSet`, a set of cases, and
`EnumMap`, a value for every case. Both are immutable and know their enum even when they hold nothing.

## EnumSet

A set holds each case at most once, always in declaration order, and offers the operations of a set rather than
those of a list: what permissions, the days a shop opens or the channels a person accepts are.

```php
use Phil\Enums\Collection\EnumSet;

$open = EnumSet::of(Day::class, Day::Monday, Day::Tuesday, Day::Friday);
$weekend = EnumSet::of(Day::class, Day::Saturday, Day::Sunday);

$open->contains(Day::Friday);           // true
$open->union($weekend)->names();        // ['Monday', 'Tuesday', 'Friday', 'Saturday', 'Sunday']
$open->complement()->names();           // ['Wednesday', 'Thursday', 'Saturday', 'Sunday']
$open->without(Day::Friday)->count();   // 2
```

| Method | Returns |
|---|---|
| `EnumSet::of($enum, ...$cases)` | The set of these cases; a case of another enum is refused. |
| `EnumSet::none($enum)`, `EnumSet::all($enum)` | The empty set, every case. |
| `EnumSet::fromBitmask($enum, $mask)` | The set a bitmask stands for. |
| `EnumSet::fromValues($enum, $values)` | The set of the cases these values — names for a pure enum — stand for, as a form or a request sends them; a value no case has is refused. |
| `contains($case)`, `isEmpty()`, `count()` | Whether it holds the case, whether it holds none, how many. |
| `with(...$cases)`, `without(...$cases)` | The set with, or without, these cases. |
| `union($other)`, `intersect($other)`, `diff($other)` | The cases in either, in both, in this one only. |
| `complement()` | The cases of the enum this set does not hold. |
| `equals($other)`, `isSubsetOf($other)`, `isSupersetOf($other)` | Comparisons between sets. |
| `cases()` | The cases, as a `CaseCollection` to filter, sort or map ([Operations](operations.md)). |
| `names()`, `values()`, `toBitmask()` | The names, the values — the names of a pure enum —, the bitmask. |
| `enum()` | The enum class. |

It is `Countable` and iterable. An operation between sets of two enums throws an `InvalidArgumentException`.

### Bit flags

When the enum is backed by integers that are powers of two — the flags of C# and Python —, a whole set fits in one
integer: the values of its cases, or'ed.

```php
enum Permission: int
{
    case Read = 1;
    case Write = 2;
    case Delete = 4;
}

EnumSet::of(Permission::class, Permission::Read, Permission::Delete)->toBitmask(); // 5
EnumSet::fromBitmask(Permission::class, 5)->names();                               // ['Read', 'Delete']
```

Mark such an enum `#[BitFlags]`: the PHPStan extension then reports a case whose value is not a power of two,
before the run time does ([PHPStan](phpstan.md)).

`toBitmask()` and `fromBitmask()` throw a `LogicException` for an enum with a case that is not a bit — pure,
backed by strings, 0, or an integer that is not a power of two —, and `fromBitmask()` throws an
`InvalidArgumentException` for a mask with a bit no case stands for. **Never renumber a flag** once masks are
stored: the values are the storage format.

## EnumMap

A map holds a value for every case: the colour of each status, the price of each plan. Iterating it yields the
cases as keys.

```php
use Phil\Enums\Collection\EnumMap;

$colours = EnumMap::total(Status::class, static fn (Status $status): string => match ($status) {
    Status::DRAFT => 'grey',
    Status::PUBLISHED => 'green',
    Status::ARCHIVED => 'brown',
});

$colours->get(Status::PUBLISHED); // 'green'

foreach ($colours as $status => $colour) {
    // $status is a Status case
}
```

**Total by construction.** `total()` calls the callback for every case, and a `match` without a `default` arm makes
PHP throw — and PHPStan report — the case it forgets: a case added to the enum fails where the map is made, not as a
missing key the day a record reaches it. `fromArray()` reads a map from an array keyed by values, as configuration
writes it, and refuses one that misses a case or has a key that is no case, naming both.

```php
EnumMap::fromArray(Status::class, ['draft' => 'grey', 'published' => 'green', 'archived' => 'brown']);
EnumMap::fromPartial(Status::class, ['published' => 'green'], 'grey');  // draft and archived are grey
```

`fromPartial()` keeps the table total while naming the choice: a forgotten case takes the default you wrote, not a
missing key.

| Method | Returns |
|---|---|
| `EnumMap::total($enum, $callback)` | The map of what the callback gives each case. |
| `EnumMap::fromArray($enum, $values)` | The map of an array keyed by the values of the cases, their names for a pure enum. |
| `EnumMap::fromPartial($enum, $values, $default)` | The same, each case the array leaves out taking the default; a key that is no case is still refused. |
| `get($case)` | The value of the case. |
| `with($case, $value)` | The same map with this value for the case. |
| `map($callback)` | The map of what the callback makes of each value; it receives the value and the case. |
| `keys()`, `values()` | The cases as a `CaseCollection`, the values as a list. |
| `toArray()` | The values keyed by the values of the cases: what `fromArray()` reads back. |
| `enum()`, `count()` | The enum class, the number of cases. |

`get()` and `with()` throw an `InvalidArgumentException` for a case of another enum.

## Cost

Enums are small, so the structures keep plain lists rather than indexes. Measured on PHP 8.5 with OPcache, for an
enum of five cases: `contains()` takes about 0.13 µs, `union()` about 2 µs, `fromBitmask()` about 2.7 µs. An index
by name would save nothing worth its code at these sizes.
