# SKU patterns

A template writes the SKU of each of its products from a pattern: literals and placeholders in braces.

| Placeholder | Replaced by |
|---|---|
| `{design}` | The code of the design, required: two designs never share a SKU |
| `{lang}` | The code of the product's language version; nothing for a design in one language |
| `{template}` | The code of the template, for a design made on two templates that share their codes |
| `{<axis>}` | The code of the product's value on that axis |

```php
SkuPattern::parse('{design}{lang}{version}{size}{colour}')->render([...]);   // NAQ001FOMBK
SkuPattern::parse('TS-{design}-{size}-{colour}');                            // TS-NAQ001-M-BK
```

A pattern holds 1 to 128 characters: letters, digits, `.`, `_`, `-` and placeholders of up to 32 lowercase letters,
digits or `_`, each once. A pattern of anything else is refused with `InvalidDefinition`.

## Glued codes and the collision check

Glued codes read without separators only when no two combinations glue into the same text: `M` + `BK` and `MB` + `K`
would. The codes of a shop are chosen so that it never happens; the planner checks anyway. Two products of one plan
with the same SKU refuse the whole plan with `PlanRefused::collision()`, which names the SKU and both products. A
pattern that leaves an axis out refuses its plan the same way, as soon as that axis has two values.

The SKU is also checked as a catalogue accepts it: 1 to 64 letters, digits, `.`, `_` or `-`, starting with a letter
or digit.

A pattern refers to codes, not to positions: a version suffix before the size and colour — `{design}{lang}{version}
{size}{colour}` — is the order the pattern gives, nothing else.
