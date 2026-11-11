<!-- phil-dev source-sha256=3c8985ddcd3ef7659a7fef2a2d9c28847e98e66a35652cb815f8c90881643145 -->
# Twig

| Nom | Nature | Donne |
|---|---|---|
| `url_display` | filtre | `example.com/shop` pour `https://www.example.com/shop/` ; un texte qui n'est pas une adresse, tel quel |
| `url_type(value, types = null)` | fonction | Le genre d'un texte, d'un `Url` ou d'un `TypedUrl` parmi les genres acceptés, ou `null` |
| `url_type_label` | filtre | Le nom de marque d'un genre international, `YouTube` ; le nom du cas pour un autre |

```twig
{% set kind = url_type(shop.social) %}
<a href="{{ shop.social }}">{{ kind|url_type_label }} — {{ shop.social|url_display }}</a>
```
