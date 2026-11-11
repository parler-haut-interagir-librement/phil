# Twig

## `format_money`

The `Money` counterpart of Twig's `format_currency`:

```twig
{{ product.price|format_money }}          {# 1 234,50 $ in fr_CA, CA$1,234.50 in en #}
{{ product.price|format_money('en_CA') }} {# $1,234.50 #}
{{ null|format_money }}                   {# nothing #}
```

| Argument | Default | Meaning |
|---|---|---|
| `locale` | the request's | The locale of the symbol, the separators and the pattern |

The filter formats with ICU's currency pattern for the locale, to the currency's own precision. Its digits reach
ICU as a float, exact up to 2^53 minor units — about 90 trillion dollars. One formatter is built per locale and kept,
for 64 locales at most, so that locales taken from requests cannot grow the memory of a worker that lives across
requests; the separators `LocalizedDecimal` reads for the forms are kept the same way.

## With `format_currency`

An application that already uses `twig/intl-extra` can format a `Money` with its filter, from the decimal:

```twig
{{ product.price.toDecimal|format_currency(product.price.currency.code) }}
```

`format_money` saves the two property reads, and works without `twig/intl-extra`.

## schema.org

| Function | Gives |
|---|---|
| `money_schema(money)`, `price_schema(money)` | A schema.org `MonetaryAmount`; the `price` and `priceCurrency` of an `Offer`; or `null` |

The data composes into a JSON-LD document printed by the template:

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` writes `<` and `>` as escapes, so no value can close the script element.
