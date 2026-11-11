# Twig

| Name | Kind | Gives |
|---|---|---|
| `url_display` | filter | `example.com/shop` for `https://www.example.com/shop/`; a text that is no address as it is |
| `url_type(value, types = null)` | function | The kind of a text, a `Url` or a `TypedUrl` among the accepted ones, or `null` |
| `url_type_label` | filter | The brand's name of an international kind, `YouTube`; the case's name of another |

```twig
{% set kind = url_type(shop.social) %}
<a href="{{ shop.social }}">{{ kind|url_type_label }} — {{ shop.social|url_display }}</a>
```
