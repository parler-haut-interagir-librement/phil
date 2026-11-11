# Extension points

What an application configures or extends. The amounts and currencies are `phil/money`'s.

## Configuration

| Option | What it extends |
|---|---|
| `currencies` | The currencies accepted, in the order a choice offers them; empty for every currency the CLDR data knows |
| `default_currency` | The currency an empty amount starts in |
| `doctrine_mapping` | Whether `Phil\Money\Money` is mapped as an ORM embeddable; `false` for a mapping of the application's own |

See [Configuration](configuration.md).

## Forms and validation

| Point | How |
|---|---|
| The currency of one field | The `currency` or `currencies` option of `MoneyType` — see [Forms](forms.md) |
| Its error | The `invalid_message` option |
| The currencies and amounts accepted | `AcceptedCurrency` and `MoneyRange` with their `min`, `max` and messages — see [Validation](validation.md) |
| The messages in other languages | The `validators` domain: the bundle ships English and French, an application adds or overrides others |
| More than the field does | A form type extension of the application's on `MoneyType` |

## Storage and Twig

| Point | How |
|---|---|
| Where an amount is kept | The `Money` embeddable, or two columns of the application's with the `phil_currency` column type — see [Doctrine](doctrine.md) |
| How an amount is shown | The `format_money` filter with its `locale`, and `money_schema` and `price_schema` for structured data — see [Twig](twig.md) |
