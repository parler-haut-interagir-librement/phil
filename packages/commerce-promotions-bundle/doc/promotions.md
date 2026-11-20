# Promotions

A promotion gives something off an order: a share of each eligible line, an amount off spread over the eligible
lines, N units for the price of X, a price for each complete bundle of product groups, or the delivery charge waived.
It applies where, to whom and when its conditions say, and some ask for a code.

```php
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;

$promotions->add(Promotion::percentOff('black-friday', 'Vendredi fou −20 %', 200_000, Currency::of('CAD'))
    ->when(channels: ['web'], startsAt: $friday, endsAt: $monday, excludedSkus: ['GIFT-CARD']));

$promotions->add(Promotion::buyNPayX('dice-3-for-2', 'Dés : 3 pour 2', 3, 2, Currency::of('CAD'))
    ->when(skus: ['DICE-D6', 'DICE-D20'], after: ['black-friday']));

$promotions->add(Promotion::bundlePrice('starter', 'Boîte de départ', [
    BundleGroup::of(['RULEBOOK']),
    BundleGroup::of(['DICE-D6', 'DICE-D20'], 2),
], Money::of(5_000, Currency::of('CAD'))));

$promotions->add(Promotion::freeShipping('ship-free', 'Livraison offerte', Currency::of('CAD'))
    ->when(deliveryMethods: ['post'], minimumSubtotal: Money::of(7_500, Currency::of('CAD'))));

$coupon = Promotion::amountOff('welcome', 'Bienvenue −5 $', Money::of(500, Currency::of('CAD')))
    ->when(requiresCode: true, perCustomerLimit: 1, minimumSubtotal: Money::of(3_000, Currency::of('CAD')));
$promotions->add($coupon);
$codes = $promotionService->generateCodes($coupon, 500, 'WELCOME-'); // 500 codes of one use each
```

`$promotions` is the `PromotionRepository`; `$promotionService` the public `Promotions` service.

## What a promotion gives

| Constructor | Gives |
|---|---|
| `percentOff(code, label, ppm, currency)` | A share off each eligible line: `100_000` ppm is 10 %, from 1 to 1 000 000 ppm |
| `amountOff(code, label, amount)` | An amount off the order, at most what the eligible lines are worth, spread over them in proportion to what each is worth — so that each line's tax follows its discount |
| `buyNPayX(code, label, buy, pay, currency)` | On each eligible line, every group of `buy` units has `buy − pay` units free; up to 1 000 |
| `bundlePrice(code, label, groups, price)` | A price for each complete bundle: 1 to 10 `BundleGroup`s, each a list of 1 to 100 products and a number of units, 1 to 1 000; no product in two groups |
| `freeShipping(code, label, currency)` | The delivery charge waived, for the methods listed or every method |

The label is what the receipt shows; `relabel()` changes it, and its translations live on `PromotionTranslation`.
Discounts are `Promotion` adjustments on the lines, never a negative price.

### Bundles

A bundle is complete when each of its groups holds its number of units; an order holds as many bundles as its
scarcest group allows. Each group takes its units from its cheapest lines first, the earliest line first on a tie.
The discount is what those units are worth, after the promotions before, less the bundle's price times the number of
bundles; it is spread over the lines that gave units, in proportion to what each gave.

**Each product keeps its own line**: a special between two products is two discounted lines, each holding its share
of the discount. A product returned alone breaks the special: it is refunded at its own value less the special's whole
discount, never below zero, and the products kept are paid in full. A product sold whole as a set — a box assembled
once and stocked as one SKU — is a composite product of the catalogue instead, one line taxed whole.

### Free shipping

A free shipping applies when the order's delivery method is one it waives, and its eligible lines reach its
minimum. `PromotionEvaluator` then leaves a neutral adjustment of zero on the order, labelled and with the promotion's
origin. `FreeShippingProcessor`, an order processor tagged after the logistics module's `ShippingProcessor` and
before the core's taxes, adds a shipping adjustment that takes back what the delivery charges: the receipt shows the
charge and its waiver. Only the first free shipping waives; a neutral delivery charge — one already included — is
never waived. Without the logistics module, the order has no delivery charge and nothing is waived.

## When it applies

`when()` sets the conditions before the promotion is stored; each argument left out keeps the current one. Each list
holds up to 500 names of 1 to 64 visible ASCII characters, without repetitions.

| Condition | Empty means |
|---|---|
| `skus` | Every product; only products are discounted, never offers or anything else the core sells |
| `excludedSkus` | Nothing excluded; an excluded product is never discounted by the promotion, even when `skus` lists it |
| `channels` | Every channel: online, in store or both is a list of channels |
| `customerGroups` | Everyone |
| `deliveryMethods` | Every delivery method, for a free shipping |
| `ownedSkus` | No condition; otherwise the customer owns one of these products, as the application's `CustomerOwnership` says |
| `requiresCode` | No code needed |
| `startsAt`, `endsAt` | Always; the start included, the end excluded |
| `usageLimit` | No limit in all |
| `perCustomerLimit` | No limit per customer; otherwise each customer gets that many uses, and a guest none |
| `minimumSubtotal` | No minimum: what the eligible lines are worth, after the promotions before, must reach it |
| `after` | Tried by code; otherwise after the promotions listed, when they are active too |
| `incompatibleWith` | Combines with every promotion; otherwise never with those listed, whichever of the two lists the other |
| `exclusive` | Not exclusive |

The channel and the customer group are the order's (`OrderModifier::changeAudience()`), the customer its
`assignCustomer()`, the delivery method its `chooseDeliveryMethod()`, the code one the buyer entered
(`OrderModifier::enterCode()`), and the moment the clock's. Codes are stored in uppercase and matched whatever the
case the buyer typed: `welcome-7kq4m2xa` unlocks `WELCOME-7KQ4M2XA`.

## How promotions add up

`PromotionProcessor` is an order processor of the core, tagged before its `TaxOrderProcessor`, its
`ManualDiscountProcessor` and the logistics module's `ShippingProcessor`, so each line is taxed once discounted and
the delivery threshold reads the discounted lines. Each time the order changes:

1. the promotions not withdrawn whose channel, group, currency, period and limit hold are sorted by code, then each
   one moved after the promotions its `after` list names, depth first, by Symfony's `BeforeAfterSorter`; a name that
   is no such promotion is ignored, and lists that loop are set aside for the order by code;
2. one applies when its code was entered and is still usable if it asks for one, the customer has uses left and owns
   what it asks for, no promotion it is incompatible with applied before it, and its eligible lines reach its minimum;
3. **each discount comes off what the promotions before it left**, and never takes a line below zero;
4. an exclusive promotion that gives something stops the ones after it.

A promotion that no longer applies — withdrawn, over, its code removed — drops off at the next change.

Each discount keeps its origin on the order: `promotion:<code>`, and `promotion:<code>:<promotion code>` when a code
unlocked it. The origins are frozen with the order when it is placed, and they are what the uses are counted from.
Each code entered is looked up once per evaluation, and the labels are read once, in one query, for every promotion
tried.

## Codes and uses

- `PromotionCode::of(code, promotion, usageLimit)`: 3 to 64 uppercase letters, digits, `_` or `-`; `1` use for a
  code given to one buyer, `null` for a code anyone may use.
- `Promotions::generateCodes(promotion, count, prefix, usageLimit = 1)` draws a batch of 1 to 10 000 codes of eight
  characters a person reads back without confusing them — no `0`, `O`, `1`, `I` or `L` —, each different from the
  others and from the codes stored.
- **Uses are counted in the transaction that places the order**: `Promotions::redeem(order)` counts a use of each
  promotion the placed order got, read from the origins of its discounts — never by evaluating the promotions
  again, which may have changed since —, of the code that unlocked it, and of the customer when the promotion limits
  its uses per customer. A promotion withdrawn after the order was placed is still counted; one that started after is
  not.

  ```php
  $entityManager->wrapInTransaction(function () use ($modifier, $order, $promotionService, $at): void {
      $modifier->place($order, $at);
      $promotionService->redeem($order);
  });
  ```

- **Each promotion is counted once per order**: a redemption row records it, with the code and the customer, so a
  retried job, or an order reopened and placed again, counts nothing twice; `redeem()` returns the redemptions it
  added.
- **A use is claimed against its limit at once**: the Doctrine adapter adds one with an `UPDATE` that applies only
  while the row is under its limit, so two orders that took the last use of a code before either was placed never
  both count it. A customer's first use inserts their row; two first uses at once collide on its primary key. The
  second order's `redeem()` throws `PromotionRefused` — or the database refuses the duplicate row —, and its
  placement is rolled back with the uses already claimed; reopened and recomputed, the order no longer gets the
  promotion. Outside a transaction, the adapter throws a `LogicException`.
- A promotion or a code at its limit applies no more; a customer at their limit no longer gets it.

## What a return reads

The discounts stay on their lines, with their origins, in the placed order. `phil/commerce-returns-bundle` reads
them: a product of a bundle returned alone is refunded at its value less the bundle's whole discount, never below
zero, the products kept being paid in full; any other discount is taken back as the share its line holds. A composite
product is returned whole, never one of its parts.

## Storage

With DoctrineBundle:

| Column of `phil_commerce_promotion` | Type | Holds |
|---|---|---|
| `code` | `VARCHAR(64)`, primary key | The promotion's code |
| `label` | `VARCHAR(255)` | What the receipt shows, in the promotion's own text |
| `exclusive` | `BOOLEAN` | Whether it stops the others |
| `action` | `VARCHAR(16)` | `percent_off`, `amount_off`, `buy_n_pay_x`, `bundle_price` or `free_shipping` |
| `parts_per_million`, `amount`, `buy_quantity`, `pay_quantity` | `INTEGER`, `BIGINT`, `SMALLINT` | What it gives; `amount` is a bundle's price; zero for what its action does not use |
| `bundle_groups` | JSON | A bundle's groups: their products and units |
| `currency`, `minimum_subtotal` | `CHAR(3)`, `BIGINT` | Its currency and minimum |
| `skus`, `excluded_skus`, `channels`, `customer_groups`, `delivery_methods`, `owned_skus` | JSON | Its conditions |
| `after_promotions`, `incompatible_with` | JSON | Its order and its incompatibilities |
| `requires_code`, `starts_at`, `ends_at`, `usage_limit`, `per_customer_limit` | `BOOLEAN`, `DATETIME` in UTC (`datetime_utc_immutable`), `INTEGER` | Its conditions |
| `uses`, `active` | `INTEGER`, `BOOLEAN` indexed | Its uses, and `false` once withdrawn |

| Column of `phil_commerce_promotion_translation` | Type | Holds |
|---|---|---|
| `id` | `INTEGER`, primary key | The row |
| `promotion`, `locale` | `VARCHAR(64)` with a foreign key, `VARCHAR(12)`; unique together | The promotion, deleted with it, and the locale |
| `label` | `VARCHAR(255)` | The label in that locale; empty until written |

| Column of `phil_commerce_promotion_code` | Type | Holds |
|---|---|---|
| `code` | `VARCHAR(64)`, primary key | The code |
| `promotion` | `VARCHAR(64)`, indexed | The promotion it unlocks |
| `usage_limit`, `uses` | `INTEGER` | Its limit and its uses |

| Column of `phil_commerce_promotion_redemption` | Type | Holds |
|---|---|---|
| `order_number`, `promotion` | `VARCHAR(64)` each, primary key together; `promotion` indexed | The placed order, and a promotion counted for it |
| `code`, `customer` | `VARCHAR(64)`, nullable | The code that unlocked it, and the customer who bought |

| Column of `phil_commerce_promotion_customer_use` | Type | Holds |
|---|---|---|
| `promotion`, `customer` | `VARCHAR(64)` each, primary key together | The promotion and the customer |
| `uses` | `INTEGER` | The uses the customer made of it |
