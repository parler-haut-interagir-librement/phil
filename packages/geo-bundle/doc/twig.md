# Twig

| Name | Kind | Gives |
|---|---|---|
| `geo_distance(from, to)` | function | The `Distance` between two `Coordinates`; `null` when either is missing |
| `format_distance(locale = null, system = 'metric')` | filter | `850 m`, `12,3 km` in `fr_CA`; `7.7 mi` with `imperial`; empty for `null` |
| `geo_attributes(position, decimals = 7)` | function, safe HTML | The attributes of a place the `distance-sort` controller sorts: its target and its position, to that many decimals |

```twig
{{ geo_distance(here, store.position)|format_distance }}
<li {{ geo_attributes(store.position, 3) }}>{{ store.name }}</li>
```

`format_distance` builds one number formatter per locale and keeps those of 64 locales at most, so that locales taken
from requests cannot grow the memory of a worker that lives across requests.

## schema.org

| Function | Gives |
|---|---|
| `geo_schema(coordinates)` | The coordinates as schema.org `GeoCoordinates`, or `null` |

The data composes into a JSON-LD document printed by the template:

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` writes `<` and `>` as escapes, so no value can close the script element.
