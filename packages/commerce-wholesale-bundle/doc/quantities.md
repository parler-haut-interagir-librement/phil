# Quantity rules

A SKU may be sold only by a minimum, up to a maximum, and in steps of an increment, for every buyer or for the buyers
of one customer group. A rule is a constraint of Symfony's Validator, so an application's form shows its message
before the cart is touched, and the core checks it again whenever a line is added, its quantity changed, or the
order's group changed.

```php
use Phil\CommerceWholesaleBundle\Application\QuantityRules;

$rules->set('DICE-D20', null, minimum: 1, maximum: 50);                       // every buyer
$rules->set('DICE-D20', 'wholesale', minimum: 12, maximum: null, increment: 12); // the wholesale group

$rules->ruleFor('DICE-D20', 'wholesale');   // the group's own rule
$rules->ruleFor('DICE-D20', 'retail');      // the rule for every group
$rules->remove('DICE-D20', 'wholesale');
```

| Member of `QuantityRule` | Rule |
|---|---|
| `sku`, `customerGroup` | 1 to 64 visible ASCII characters; a `null` group holds for every group without a rule of its own |
| `minimum()` | 1 to 1 000 000 units |
| `maximum()` | At least the minimum, at most 1 000 000 000; `null` for no limit |
| `increment()` | 1 to 1 000 000; the steps count from the minimum: a minimum of 6 and an increment of 6 allow 6, 12, 18 |
| `breachOf(quantity)` | `BelowMinimum`, `AboveMaximum`, `OffIncrement`, the first it breaks; `null` when allowed |
| `nextAllowed(quantity)` | The closest allowed quantity at or above it; `null` when the maximum stops it |
| `change()` | New terms, checked as a new rule's |

A group's own rule replaces the rule for every group, it does not add to it. A SKU without a rule takes any quantity.

## The constraint

`AllowedQuantity` validates a `RequestedQuantity` — SKU, quantity, customer group. An application's form object
returns one from a getter carrying the constraint:

```php
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantity;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\RequestedQuantity;

final class AddToCart
{
    public string $sku = '';
    public int $quantity = 1;
    public ?string $customerGroup = null;

    #[AllowedQuantity]
    public function requested(): RequestedQuantity
    {
        return new RequestedQuantity($this->sku, $this->quantity, $this->customerGroup);
    }
}
```

| Error | Code | Message key in the `validators` domain |
|---|---|---|
| Under the minimum | `AllowedQuantity::BELOW_MINIMUM_ERROR` | `phil_commerce_wholesale.quantity.below_minimum` |
| Over the maximum | `AllowedQuantity::ABOVE_MAXIMUM_ERROR` | `phil_commerce_wholesale.quantity.above_maximum` |
| Between two steps | `AllowedQuantity::OFF_INCREMENT_ERROR` | `phil_commerce_wholesale.quantity.off_increment` |

The bundle translates the keys in English and French, in `translations/validators.en.php` and
`validators.fr.php`; the parameters `{{ sku }}`, `{{ quantity }}`, `{{ minimum }}`, `{{ maximum }}`,
`{{ increment }}` and `{{ allowed }}` — the closest quantity allowed — let an application write its own messages,
through the constraint's `belowMinimumMessage`, `aboveMaximumMessage` and `offIncrementMessage`. FrameworkBundle's
translator must be enabled for the messages to be translated.

## In the cart

`QuantityGuard` is a unit price resolver of the core, asked before the catalogue's price books and `GroupPrices`.
The core asks its resolvers for a line's whole quantity — what the line already holds plus what is added — and for
the order's customer group, before it changes the order. The guard gives no price: it lets an allowed quantity
through to the resolvers after it, and refuses another with `QuantityRefused`, whose `messages` are the validator's,
translated in the request's locale. The order is left as it was.

- Only products are checked: their reference names the SKU. Offers and anything else sold are left alone.
- A configured line — the same SKU with other option values — is checked on its own quantity.
- Changing the order's group checks every line again for the new group.

## Storage

With DoctrineBundle, a rule is one row of `phil_commerce_wholesale_quantity_rule` — `sku` and `customer_group`
(primary key; an empty group for every group), `minimum`, `maximum` (nullable), `increment`.
