# Selecting cases

A form, a validator constraint and a template often need the same subset of an enum: the statuses a customer may
choose, the shapes a plan allows. `CaseSelection` declares that subset once, from four criteria, and every part of
the bundle reads it the same way:

| Criterion | Keeps |
|---|---|
| `only` | The cases among those given. |
| `except` | The cases other than those given. |
| `groups` | The cases in at least one of these groups — the values of their `#[Group]` metadata. |
| `whereMeta` | The cases whose meta properties have these values, compared strictly: meta property class to value. |

The criteria combine; an empty one does not narrow, and with none every case is selected. The cases keep the
enum's declaration order.

```php
use Phil\Enums\Attribute\Group;

enum OrderStatus: string
{
    #[Group('customer')] #[Group('staff')]
    case PENDING = 'pending';

    #[Group('customer')]
    case IN_TRANSIT = 'in_transit';

    #[Group('staff')]
    case CANCELLED = 'cancelled';
}

$selection = new CaseSelection(OrderStatus::class, groups: ['customer']);
$selection->cases()->all(); // [OrderStatus::PENDING, OrderStatus::IN_TRANSIT]
```

**Groups are what `Group` is for**: name the subsets on the enum, next to its cases, rather than repeating lists
of cases in every form and constraint. A case may belong to several groups.

`CaseSelection::fromOptions()` builds a selection from loosely typed options — a form's, a template's — and
refuses with an `InvalidArgumentException` an enum class that is not an enum, a list holding something else than
cases, a group that is not a string, or a `whereMeta` key that is not a meta property class.
