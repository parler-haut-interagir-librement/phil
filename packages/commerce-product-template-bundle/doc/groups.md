# Groups

The products of a design on a template form a **group**: the page a buyer sees, with a selector per axis — size,
colour, version —, while each product stays a product of the catalogue with its own SKU, price and stock.

| Type | Holds |
|---|---|
| `ProductGroup` | Its identifier `<design>:<template>`, its design and template codes, whether it is active |
| `GroupMember` | A SKU of the group, the code of its value on each axis, its language version, whether it is active, the path of its mockup per face |

Groups and members are created and updated by the generation only; an application reads them.

## The page of a group

```php
$page = $groups->page('NAQ001', 'unisex', 'fr');
```

`ProductGroups::page()` returns a `GroupPage`, or `null` for a withdrawn group:

| Member | What it is |
|---|---|
| `id`, `name` | The group's identifier, and `Design – Template` in the locale |
| `axes` | For each axis of the template, in order: its code, label and the values some active member has, each with its code, label, `hex` and `photo` swatch |
| `members` | Each active member: SKU, values by axis, language version and mockups by face |
| `skuFor($values, $language)` | The SKU of the active member of those values and language, or `null` |

A selector never offers a combination the template excludes, nor one the design does not allow: no member has it.
A Stimulus controller of the application reads the members once and finds the SKU, and the mockup, of each choice
without asking the server.
