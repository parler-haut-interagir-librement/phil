# Planning

`VariantPlanner::plan($template, $design)` gives the products of a design made on a template, as a list of
`Variant`: by language version, then in the order of the template's combinations.

## What a plan holds

A product for each language version of the design — or one, for a design in one language — and each combination
that the template makes, the design allows, and some channel sells. Each `Variant` has:

| Member | What it is |
|---|---|
| `sku` | The pattern rendered with the design, language, template and value codes |
| `design`, `template`, `language`, `combination` | What it was planned from |
| `price` | The template's price of its combination |
| `channels` | The channels that sell it; every channel when empty |
| `artworks` | A `PlacedArtwork` per zone it prints, in the order of the template's faces and zones |
| `name($defaultLocale)` | `Design – Template (value, value, value)` in its language's locale, else the default |
| `attributes()` | `design`, `template`, `language` when it has one, and the code of each value by axis |
| `photo($face)` | The photo of the blank on that face: the first value of its combination that has one |

**Channels**: the values that name channels restrict their products to the channels they all share. A combination
whose values share none — a value sold only online with one sold only at a booth — is not planned.

## The same input, the same plan

A plan depends on its template and design only, and lists its products in a stable order. A generator that runs it
again after a change finds the products it already made by their SKUs, updates them, makes the new ones and
withdraws those the plan no longer has. The planner itself never stores anything.

## What a plan refuses

`PlanRefused`, before any product is given:

| Factory | When |
|---|---|
| `templateNotAllowed()` | The design is not made on the template |
| `collision()` | Two products share a SKU: the message names the SKU and both products |
| `invalidSku()` | A SKU is not 1 to 64 letters, digits, `.`, `_` or `-`, starting with a letter or digit |
| `unknownZone()` | An artwork names a zone its face lacks |
| `ambiguousArtwork()` | Two artworks apply equally to a zone of a product |
| `tooMany()` | The plan has more products than the planner's limit |

## Limits

A template has at most 8 axes and 10 000 combinations, a design at most 8 language versions, so a plan stays bounded.
The planner's limit is 20 000 products by default, and lower when given: `new VariantPlanner(5_000)`. Planning 5 400
products takes a few tens of milliseconds ([Testing](testing.md)).
