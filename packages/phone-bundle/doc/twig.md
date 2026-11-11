# Twig

| Filter | Gives |
|---|---|
| `phone(format = 'international')` | The number in `international`, `national`, `e164` or `rfc3966`; an unreadable text as it is, empty for `null` |
| `phone_link` | The `tel:` address, or `null` for an unreadable text |

A text is read in the bundle's `default_region`.

```twig
{% set link = shop.phone|phone_link %}
{% if link %}<a href="{{ link }}">{{ shop.phone|phone('national') }}</a>{% endif %}
```
