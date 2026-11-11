# Forms

`Phil\MoneyBundle\Infrastructure\Form\MoneyType` edits a `?Money`.

```php
$builder->add('price', MoneyType::class);                                   // the accepted currencies
$builder->add('price', MoneyType::class, ['currency' => 'CAD']);            // one currency, no choice
$builder->add('price', MoneyType::class, ['currencies' => ['CAD', 'USD']]); // these currencies
```

## Options

| Option | Default | Meaning |
|---|---|---|
| `currency` | `null` | One currency code: the form renders no choice and the submitted amount is in it, even when the form was filled from an amount in another currency |
| `currencies` | `null` | The codes offered, in order; the accepted ones by default. A single one is fixed, as with `currency`. |
| `invalid_message` | `This value is not a valid money amount.` | The error of an amount that cannot be read |
| `required` | `true` | Passed to the `amount` field |

An unknown code in `currency` or `currencies` throws `UnknownCurrency` when the form is built; an empty
`currencies` list is refused. Offering a currency is not accepting it: pair the field with `#[AcceptedCurrency]`
when `currencies` is wider than the configuration.

## The fields

- **`amount`**, a `TextType` with `inputmode="decimal"` — a numeric keyboard on a phone, without the
  locale-blind parsing of `<input type="number">`.
- **`currency`**, a `ChoiceType` of codes labelled by their names in the request's locale (`dollar canadien`),
  rendered only when more than one currency is offered.

Both have no label of their own: the form's label covers the pair. The block prefix is `phil_money`, for a form
theme that lays the two fields out on one line.

## How the amount is read

The amount is read as a string in the request's locale, and never through a float:

| Locale | Accepted | Read as |
|---|---|---|
| `fr_CA` | `1 234,50`, `1234.50`, `−12,5` | `1234.50`, `1234.50`, `-12.5` |
| `en` | `1,234.50`, `12.5`, `1 234.50` | `1234.50`, `12.5`, `1234.50` |
| `de` | `1.234,50`, `1.234` | `1234.50`, `1234` |
| `en_IN` | `12,34,567.50` | `1234567.50` |

| Locale | Refused | Why |
|---|---|---|
| `en` | `12,50` | A grouping separator before two digits |
| `de` | `12.50` | The dot is the grouping separator in `de`, and two digits are not a group |
| `en` | `1,2,3`, `1234,567`, `1,234 567` | Groups of the wrong size, or two kinds of grouping |

The decimal point is the locale's decimal separator, and also a dot when the dot is not the locale's grouping
separator. Grouping is accepted only before the decimal point and in groups of the locale's sizes — three digits,
or three then two in `en_IN` — separated by the locale's grouping separator or a space, the same one throughout.
A separator anywhere else makes the amount unreadable rather than being dropped, so `12,50` typed in `en` is never
read as 1250. The minus sign `−` stands for `-`. The amount is then read by `Money::ofDecimal()` in the chosen
currency. A locale whose grouping separator is the dot reads `1.234` as one thousand two hundred thirty-four, as a
person writing in that locale means.

An empty amount gives `null`. An amount that cannot be read, or that is finer than the currency counts (`12,345`
dollars), fails the form with `invalid_message`; the bundle translates the default message into French. A currency
that is not offered fails the `currency` field with the choice's own error.

The amount is shown back with the locale's decimal separator and without grouping, so that the field reads back
what it shows: `-1234,50` in `fr_CA`.

## An empty field

The `currency` choice starts on the default accepted currency when it is offered, on the first offered currency
otherwise. With an open `currencies` configuration, the choice offers the currencies in current use — legal tender
in at least one country today — and not the historical codes the CLDR data also knows; pass `currencies` to offer
others.
