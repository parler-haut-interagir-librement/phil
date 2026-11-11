# Twig

| Name | Kind | Gives |
|---|---|---|
| `address(locale = null)` | filter | The address on its lines, joined by line breaks — `|nl2br` shows them —; empty for `null` |
| `address_lines(locale = null)` | filter | The lines |
| `address_label(origin, locale = null)` | filter | The address for a postal label sent from a country |
| `country_name(code, locale = null)` | function | The country's name |

The language is the request's unless one is given: in Japanese, a Japanese address takes its own format.

## schema.org

| Function | Gives |
|---|---|
| `address_schema(address)` | The address as a schema.org `PostalAddress`, or `null` |

The data composes into a JSON-LD document printed by the template:

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` writes `<` and `>` as escapes, so no value can close the script element.
