# Customer groups

Customer groups are the model of wholesale selling: a group sells on terms of its own, and each buyer belongs to one
group at most. The buyer is named as the application names it — an account, or the tenant of a multi-tenant
application, a shop buying for its own customers.

```php
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;

$groups->addGroup(CustomerGroup::of('wholesale', 'Grossistes', Currency::of('CAD'),
    discount: 250_000, minimumOrder: Money::of(50_000, Currency::of('CAD'))));

$customerGroups->join($tenantId, 'wholesale');

// When the buyer starts an order
$modifier->changeAudience($order, 'web', $customerGroups->groupOf($tenantId), $context);

// Before placing it
if ($customerGroups->shortOfMinimum($order)->isPositive()) { /* not yet */ }
```

| Member of `CustomerGroup` | Rule |
|---|---|
| `code` | 1 to 64 visible ASCII characters, as the core's order names its customer group |
| `name()`, `rename()` | Not blank |
| `discount()`, `changeTerms()` | 0 to 999 999 parts per million off the base prices: `250_000` is 25 % |
| `minimumOrder()` | Zero or more, in the group's currency: what the order's lines must reach |
| `priceFrom(Money)` | The base price less the share, rounded to the closest minor unit |
| `withdraw()`, `restore()` | A withdrawn group gives no price, no minimum, and is no one's group |

| Method of `CustomerGroups` | Effect |
|---|---|
| `join(buyer, groupCode)` | Puts the buyer in the group, or moves them to it; an unknown group throws `WholesaleRefused` |
| `leave(buyer)` | Takes the buyer out of their group |
| `groupOf(buyer)` | The buyer's group while it is active; `null` otherwise |
| `shortOfMinimum(order)` | What the order's lines still lack to reach its group's minimum; zero once they do, or without a group |

## Which price a product takes

`GroupPrices` is a unit price resolver of the core, tagged after the catalogue's `PriceBookPrices`: it is asked after
the price books. So, for the order's customer group:

1. a price book naming the group — its own price list — gives the price, when it prices the product;
2. otherwise the group's share comes off the product's base price;
3. otherwise, for a group without a share, the base price.

Only products are priced by the group; offers and anything else sold keep their own prices.

## Storage

With DoctrineBundle, a group is one row of `phil_commerce_wholesale_group` — `code` (primary key), `name`,
`currency`, `discount`, `minimum_order`, `active` —, and a membership one row of
`phil_commerce_wholesale_membership` — `buyer` (primary key), `customer_group` (indexed).
