# Manual discounts

An employee lowers a price by hand, at the counter or in the store's back office, with a reason: a fixed amount or a
share, on one line or on the whole order. The core holds the discount; a point of sale shows it.

```php
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;

$modifier->grantManualDiscount($order, ManualDiscount::amount('box', $twoDollars, 'Damaged box', 'employee-7', $diceLine));
$modifier->grantManualDiscount($order, ManualDiscount::share('regular', 1_000, 'Regular customer', 'employee-7')); // 10 % off the order
$modifier->withdrawManualDiscount($order, 'box');
```

- **A discount has a key**, up to 32 letters, digits, `_` or `-`, unique within its order, to withdraw it; a reason
  on one line; and the stable identifier of who granted it.
- **A share is in basis points**, 1 to 10,000, rounded down to the minor unit; an amount is positive, in the order's
  currency.
- **Only a cart takes a discount**, like any change of its lines.
- **`ManualDiscountProcessor` turns the discounts into `Promotion` adjustments** of origin `manual:<key>` and label
  the reason, after the options and the catalogue's promotions and before the taxes, and leaves the other promotions
  alone. A line is taxed once discounted.
- **A discount on the whole order is shared between its lines** in proportion to what is left of each, through
  `Money::allocate()`, so each line is taxed on its own discounted amount.
- **A discount never takes more than what it applies to**: what is left of the line once its options and earlier
  promotions and discounts apply, never below zero.

The discounts are stored with the order, in the JSON column `manual_discounts`. Whether an employee may grant a
discount, and up to how much, is the application's decision, through its security voters.
