# Order, parsing, defaults and transitions

Four traits for what an enum often needs around its cases: their order, a lenient reading of outside input, a
default case, and the moves between them. `AsEnumerableEnumTrait` includes all four. A fifth topic needs no trait:
deprecated cases.

## Order: AsOrdinalEnumTrait

The ordinal of a case is its position in the source file, from 0. Statuses that follow one another, levels and
priorities compare by it.

```php
enum Stage: string
{
    use AsOrdinalEnumTrait;

    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
}

Stage::Review->ordinal();                         // 1
Stage::Review->next();                            // Stage::Published
Stage::Published->next();                         // null
Stage::Published->next(cycle: true);              // Stage::Draft
Stage::Draft->isBefore(Stage::Published);         // true
Stage::Review->isBetween(Stage::Draft, Stage::Review); // true, both ends included
```

| Method | Returns |
|---|---|
| `ordinal()` | The position of the case, from 0. |
| `next(bool $cycle = false)`, `previous(bool $cycle = false)` | The case after or before; `null` past an end, or the case at the other end when `$cycle` is true. |
| `compareTo($other)` | Less than, equal to or more than 0, as `<=>`: usable as a `usort()` callback. |
| `isBefore($other)`, `isAfter($other)` | Strict comparisons by position. |
| `isBetween($from, $to)` | Whether the case lies between the two, both included. |

**The order is the source file's.** Reordering the cases changes every ordinal: store the value of a case, never
its ordinal.

## Parsing: AsParsableEnumTrait and Alias

`from()` reads one exact value. `parse()` reads what outside input sends — a query string, an import, an older
API —: a value, a name, or one of the case's `#[Alias]` spellings, and without regard to letter case when asked.

```php
use Phil\Enums\Attribute\Alias;

enum Priority: string
{
    use AsParsableEnumTrait;

    #[Alias('minor')]
    case Low = 'low';

    #[Alias('major')] #[Alias('3')]
    case High = 'high';
}

Priority::parse('low');                     // Priority::Low, by value
Priority::parse('High');                    // Priority::High, by name
Priority::parse('major');                   // Priority::High, by alias
Priority::parse(3);                         // Priority::High, the alias '3'
Priority::tryParse('MAJOR');                // null
Priority::tryParse('MAJOR', ignoreCase: true); // Priority::High
Priority::parse('extreme');                 // ValueError
```

- **Values first, then names, then aliases.** Each kind is tried on every case before the next, so a value is
  never taken for the alias another case carries.
- **An alias is a string.** `Alias` is repeatable; write an alias that looks like a number as a string,
  `#[Alias('3')]`. The input is compared as a string, so `parse(3)` and `parse('3')` agree.
- **Letter case is ASCII.** `ignoreCase` compares with `strcasecmp()`.
- **Any enum, without the trait.** `Reflection::parse(Priority::class, 'major')` reads the same way, for code that
  receives an enum class — a controller argument resolver, an importer.

## Default case: AsDefaultableEnumTrait and DefaultCase

```php
use Phil\Enums\Attribute\DefaultCase;

enum Visibility: string
{
    use AsDefaultableEnumTrait;

    case Public = 'public';

    #[DefaultCase]
    case Private = 'private';
}

Visibility::default();    // Visibility::Private
Visibility::tryDefault(); // Visibility::Private, or null for an enum that marks none
```

The default is declared once on the enum rather than in every entity and form. `default()` throws a
`LogicException` when the enum marks no case; both methods throw one when it marks more than one. The marked case
is read once per process.

## Transitions: AsStatefulEnumTrait and TransitionsTo

A lifecycle declared on the cases: `#[TransitionsTo]` names where a case may move.

```php
use Phil\Enums\Attribute\TransitionsTo;

enum OrderStatus: string
{
    use AsStatefulEnumTrait;

    #[TransitionsTo(self::Paid, self::Cancelled)]
    case Pending = 'pending';

    #[TransitionsTo(self::Shipped)]
    case Paid = 'paid';

    case Shipped = 'shipped';

    case Cancelled = 'cancelled';
}

OrderStatus::Pending->canTransitionTo(OrderStatus::Paid);   // true
OrderStatus::Pending->nextStates()->names();                // ['Paid', 'Cancelled']
OrderStatus::Shipped->isFinal();                            // true
OrderStatus::initialStates()->all();                        // [OrderStatus::Pending]
OrderStatus::transitions();                                 // [[Pending, Paid], [Pending, Cancelled], [Paid, Shipped]]
```

| Method | Returns |
|---|---|
| `canTransitionTo($to)` | Whether the case may move to the other. |
| `nextStates()` | The cases it may move to, in declaration order, as a `CaseCollection`. |
| `isFinal()` | Whether it moves nowhere: a case without the attribute. |
| `initialStates()` | The cases no case moves to, where a lifecycle starts. |
| `transitions()` | Every move, as pairs of cases. |

A transition to a case of another enum throws a `LogicException` when the case is first read. The trait answers
what a template or a validator asks — may this order be cancelled? —; for guards, events, history or an audit trail,
Symfony Workflow reads the same declaration through `phil/enums-bundle`, whose `#[Transition]` constraint refuses a
change of state the declaration does not allow.

## Deprecated cases

PHP 8.4's `#[\Deprecated]` attribute applies to enum cases: writing `Status::LEGACY` then raises a deprecation
notice. The library reads it, so that a form stops offering a case while records that hold it keep reading it.

```php
enum Status: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    case Active = 'active';

    #[\Deprecated('Use Active')]
    case Legacy = 'legacy';
}

Status::withoutDeprecated()->all();         // [Status::Active]
Status::collect()->deprecated()->all();     // [Status::Legacy]
Status::from('legacy')->isDeprecated();     // true
```

`isDeprecated()` comes with `AsMetadatableEnumTrait`; `withoutDeprecated()` with `AsCollectableEnumTrait` and
`CaseCollection`. Reading a deprecated case through `from()`, `cases()` or a collection raises no notice; only
writing its constant does.
