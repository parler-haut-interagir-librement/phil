# Operations on the cases

Count the cases of an enum, take the first or the last, filter them by a callback or by a meta property, and sort
them. `AsCollectableEnumTrait` puts the operations on the enum; those that keep several cases return a
`CaseCollection`, which offers them all again, so that a selection can be refined step by step.

```php
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsCollectableEnumTrait;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Label::class, Visible::class)]
enum Status: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    #[Label('Draft')] #[Visible(true)]
    case DRAFT = 'draft';

    #[Label('Published')] #[Visible(true)]
    case PUBLISHED = 'published';

    #[Label('Archived')] #[Visible(false)]
    case ARCHIVED = 'archived';
}

Status::count();                                     // 3
Status::first();                                     // Status::DRAFT
Status::last(fn (Status $s): bool => $s->visible()); // Status::PUBLISHED
Status::whereMeta(Visible::class, true)->options();  // ['DRAFT' => 'draft', 'PUBLISHED' => 'published']
Status::sortBy(fn (Status $s): string => $s->label(), descending: true)->names();
                                                     // ['PUBLISHED', 'DRAFT', 'ARCHIVED']
```

## On the enum

| Method | Returns |
|---|---|
| `collect()` | Every case, in declaration order, as a `CaseCollection`. |
| `count()` | The number of cases. |
| `first(?callable $accept = null)` | The first case, or the first the callback accepts; `null` when there is none. |
| `last(?callable $accept = null)` | The last case, or the last the callback accepts; `null` when there is none. |
| `filter(callable $accept)` | The cases the callback accepts. |
| `whereMeta(string $metaProperty, mixed $value)` | The cases whose meta property has this value. |
| `inGroup(string ...$groups)` | The cases in at least one of these groups: the values of their `#[Group]` metadata, a case may carry several. |
| `sortBy(callable $key, bool\|SortDirection $descending = false)` | The cases ordered by the key the callback gives each one. |
| `groupBy(callable $key)`, `groupByMeta(string $metaProperty)` | The cases by key, as collections. |
| `withoutDeprecated()` | The cases without PHP's `#[\Deprecated]` ([Order, parsing and defaults](order-and-parsing.md)). |
| `random(?Randomizer $randomizer = null)` | A case drawn at random, each as often as its `Weight`. |

## On a collection

`CaseCollection` has the same methods, plus:

| Method | Returns |
|---|---|
| `all()` | The cases, as a list. |
| `isEmpty()` | Whether there is no case. |
| `contains(UnitEnum $case)` | Whether the collection holds the case. |
| `reject(callable $refuse)` | The cases the callback refuses. |
| `only(UnitEnum ...$cases)`, `except(UnitEnum ...$cases)` | The cases among, or other than, those given — in the order of the collection, not of the arguments. |
| `sortByName(bool\|SortDirection $descending = false)` | The cases ordered by name. |
| `sortByValue(bool\|SortDirection $descending = false)` | The cases ordered by value, or by name for a pure enum. |
| `reverse()` | The cases in reverse order. |
| `map(callable $map)` | What the callback makes of each case, as a list. |
| `names()`, `values()`, `options()` | As the traits of the same names, for the cases kept: `options()` of a filtered collection is the choice list of a form that offers only those cases. |
| `isBacked()` | Whether the cases are those of a backed enum; `false` for an empty collection. |
| `partition(callable $accept)` | Two collections: the cases the callback accepts, then those it refuses. |
| `keyBy(callable $key)` | The cases keyed by what the callback gives each one; two cases of one key throw a `LogicException`. |
| `pluck(string $metaProperty)` | The value of a meta property for each case, its default for a case without it. |
| `deprecated()` | The cases with PHP's `#[\Deprecated]`. |

It is `Countable` and iterable, and can be built from any list of cases of one enum with `CaseCollection::of()`.

## Grouping

`groupBy()` takes a callback that gives each case a key, or a list of keys to put the case in several groups;
`groupByMeta()` reads a meta property, and puts a case under every value of a repeatable one such as `Group`. The
groups come in the order their keys first come up, each a `CaseCollection`.

```php
Planet::groupByMeta(Group::class);
// ['inner' => [Mars, Earth, Mercury, Venus], 'home' => [Earth], 'dwarf' => [Pluto]]

Status::groupBy(fn (Status $s): string => $s->visible() ? 'shown' : 'hidden');
```

A case without the attribute goes under the meta property's default value, or under no group when that default
is `null`. A value that cannot be an array key — neither an integer nor a string — throws an
`UnexpectedValueException`. `groupByMeta(Group::class)` is what a form needs for its `<optgroup>`s.

## Random draws

`random()` draws a case, each as often as its `#[Weight]`: a case of weight 3 comes up three times as often as a
case of weight 1, a case of weight 0 never, and a case without the attribute weighs 1.

```php
use Phil\Enums\Attribute\Weight;

enum Variant: string
{
    use AsCollectableEnumTrait;

    #[Weight(80)]
    case Control = 'control';

    #[Weight(20)]
    case Treatment = 'treatment';
}

Variant::random();                                                   // Control four times out of five
Variant::random(new Randomizer(new Xoshiro256StarStar($seed)));      // the same draw for the same seed
```

`random()` returns `null` when there is no case or every weight is 0. Without an argument it draws from PHP's
secure engine; pass a `Random\Randomizer` on a seeded engine for a draw a test can repeat. A negative weight
throws an `InvalidArgumentException` when the attribute is read. `Weight` need not be listed in `#[Meta]` to be
drawn by; list it to read it with `->weight()`.

## The rules

- **Immutable.** Every operation returns a new collection; the one it was called on is unchanged.
- **Always a list.** The cases are indexed from 0 in their current order: declaration order, until a sort or
  `reverse()` changes it. A filter never leaves holes in the keys.
- **Strict comparisons.** `whereMeta()` compares with `===`, so `whereMeta(Mass::class, '60')` does not find a
  mass of `60`. A case without the attribute has the meta property's default value — `null` unless the property
  defines another —, so `whereMeta(Mass::class, null)` finds the cases without it.
- **A stable sort.** `sortBy()` compares the keys with `<=>`, and two cases of equal key keep their order,
  ascending or descending.
- **Groups are named subsets.** `Group` is repeatable, and `inGroup()` reads every group a case carries: declare
  the subsets an enum offers — to customers, to staff — once, on its cases, rather than as lists in every form.
- **One direction everywhere.** Each sort takes `true` or a `Sort\SortDirection`, the enum a query string, a
  repository and SQL share: `SortDirection::parse('DESC', ignoreCase: true)` reads it leniently — `asc`, `desc`,
  `ascending`, `descending`, `up`, `down` —, `sql()` gives `ASC` or `DESC`, `compare($a, $b)` sorts with
  `usort()`, `reverse()` turns it, `descendingIf($bool)` builds it.
- **The metadata is read once.** `whereMeta()` and a `sortBy()` on an accessor read the meta property values from
  the reader's memory after the first reading of each case ([Metadata](metadata.md)).
