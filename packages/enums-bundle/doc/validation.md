# Validation

`EnumCase` accepts a value only when it is a case of a [selection](selection.md):

```php
use Phil\EnumsBundle\Infrastructure\Validator\EnumCase;

final class OrderForm
{
    #[EnumCase(OrderStatus::class, inGroup: 'customer', except: [OrderStatus::CANCELLED])]
    public ?OrderStatus $status = null;
}
```

| Argument | Type | Default | Meaning |
|---|---|---|---|
| `enum` | class-string | required | The enum. |
| `only`, `except` | list of cases | `[]` | As in a selection. |
| `inGroup` | string or list of strings | `[]` | The groups; named so because `groups` is the constraint's validation groups. |
| `whereMeta` | map of meta property class to value | `[]` | As in a selection. |
| `message` | string | `The value {{ value }} is not an allowed choice.` | The violation message. |
| `groups`, `payload` | | | As for every constraint. |

## What is validated

- `null` is valid, as for every constraint but `NotNull`.
- A case of the enum is checked against the selection.
- A backed value — `'in_transit'` — is read as its case first, and so is a case name for a pure enum. For an enum
  backed by integers, a string that writes an integer as PHP does — `'3'` or `'-3'`, as a query string brings it —
  is read as that integer.
- Anything else — another enum's case, an array, an integer for a pure enum or for an enum backed by strings,
  `'03'`, `'3.0'` or `' 3'` for an enum backed by integers — is not a case.

| Code | When |
|---|---|
| `EnumCase::NOT_A_CASE_ERROR` | The value stands for no case of the enum; `{{ value }}` is the value. |
| `EnumCase::NOT_ALLOWED_ERROR` | The value is a case outside the selection; `{{ value }}` is the case name. |

## Changes of state: Transition

`Transition` refuses a change of state the enum does not declare with `#[TransitionsTo]`
([phil/enums](../../enums/doc/order-and-parsing.md)).

```php
use Phil\EnumsBundle\Infrastructure\Validator\Transition;

#[ORM\Column(enumType: Delivery::class)]
#[Transition]
public Delivery $status = Delivery::Ordered;

// A DTO that keeps the case it was loaded with:
#[Transition(from: 'loadedStatus')]
public ?Delivery $status = null;
```

The previous case is read from the property `from` names, or else from what Doctrine loaded for a managed entity —
the backed value read back as its case. Staying in the same case is valid. An object without a previous case — a
new entity, a `null` column — must start in an initial case, one no case moves to, unless `fromInitial` is `false`.

| Option | Meaning |
|---|---|
| `from` | The property of the object that holds the previous case; `null` asks Doctrine. |
| `fromInitial` | Whether an object without a previous case must start in an initial case; `true` by default. |
| `message`, `initialMessage` | The two messages, with `{{ from }}` and `{{ to }}`. |

| Code | Raised |
|---|---|
| `Transition::NOT_ALLOWED_ERROR` | The previous case does not move to the new one. |
| `Transition::NOT_INITIAL_ERROR` | There is no previous case, and the new one is not initial. |

A value that is not a case throws `UnexpectedValueException`; `null` is valid. Without DoctrineBundle, only `from`
gives a previous case.

## Sets: EnumSetOf

`EnumSetOf` is the constraint of an `EnumSet`: every case of the set in a selection, and a number of cases between
bounds.

```php
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOf;

#[EnumSetOf(Permission::class, inGroup: 'basic', min: 1, max: 2)]
public EnumSet $permissions;
```

| Option | Meaning |
|---|---|
| `enum`, `only`, `except`, `inGroup`, `whereMeta` | The selection, as for `EnumCase`. |
| `min`, `max` | The bounds on the number of cases, both included; `null` for none. A negative bound, or a minimum above the maximum, is refused when the constraint is built. |
| `message`, `notAllowedMessage`, `minMessage`, `maxMessage` | The four messages; the last two are plural, with `{{ count }}` and `{{ limit }}`. |

| Code | Raised |
|---|---|
| `EnumSetOf::NOT_A_SET_ERROR` | Once, for a value that is not an `EnumSet` of the enum; nothing else is checked. |
| `EnumSetOf::NOT_ALLOWED_ERROR` | Once per case outside the selection. |
| `EnumSetOf::TOO_FEW_ERROR`, `EnumSetOf::TOO_MANY_ERROR` | When the count is outside the bounds. |

`null` is valid, as for every constraint but `NotNull`.
