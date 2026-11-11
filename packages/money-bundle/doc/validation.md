# Validation

Both constraints accept `null`, as every constraint but `NotNull` does; add `NotNull` for a required amount.

## `AcceptedCurrency`

The value is in a currency of `phil_money.currencies`. It accepts a `Money`, a `Currency` or a currency code; a
code the currency data does not know is a violation, not an exception.

```php
#[AcceptedCurrency]
public ?Money $price = null;

#[AcceptedCurrency(message: 'We do not bill in {{ currency }}.')]
public ?string $billingCurrency = null;
```

| Option | Default |
|---|---|
| `message` | `The currency {{ currency }} is not accepted.` — French: `La devise {{ currency }} n’est pas acceptée.` |

Code: `AcceptedCurrency::NOT_ACCEPTED_ERROR`. With an empty `currencies` list, every known currency is accepted.

## `MoneyRange`

The `Money` lies between `min` and `max`, both inclusive, decimals read **in the money's own currency**:
`min: '0'` refuses a negative amount in any currency; `max: '500'` caps it at 500 units of whichever currency it is
in. At least one bound is given.

```php
#[MoneyRange(min: '0.01')]
public ?Money $price = null;

#[MoneyRange(min: '0', max: '10000', maxMessage: 'Call us for amounts above {{ limit }}.')]
public ?Money $deposit = null;
```

| Option | Default |
|---|---|
| `min`, `max` | `null` |
| `minMessage` | `This amount should be {{ limit }} or more.` — French: `Ce montant doit être d’au moins {{ limit }}.` |
| `maxMessage` | `This amount should be {{ limit }} or less.` — French: `Ce montant doit être d’au plus {{ limit }}.` |

A bound is a decimal, `-?\d+(\.\d+)?`; anything else throws `ConstraintDefinitionException` when the constraint is
built. A bound finer than the money's currency is rounded towards the inside of the range, up for `min` and down for
`max`: `min: '0.01'` on a yen amount asks for 1 yen, and `max: '9.99'` allows 9. A bound beyond every amount the
currency can hold refuses every amount when it is a minimum above them or a maximum below them, and no amount
otherwise. No bound throws for an amount a user submits.

`{{ limit }}` is the bound as a decimal, rounded in the money's currency, and a code: `0.00 CAD`, `1 JPY`. Codes:
`MoneyRange::TOO_LOW_ERROR`, `MoneyRange::TOO_HIGH_ERROR`.

The messages are in the `validators` domain, in `translations/validators.en.php` and `validators.fr.php`.
