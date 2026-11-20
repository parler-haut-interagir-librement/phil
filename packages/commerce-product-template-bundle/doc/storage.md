# Storage

With DoctrineBundle, the module maps four entities by attributes, under `Phil\CommerceProductTemplateBundle\Domain\Model`.
The application creates their tables with its migrations.

| Table | Entity | Columns |
|---|---|---|
| `phil_commerce_product_template` | `ProductTemplate` | `code` (key), `definition` (JSON), `kind`, `tax_category`, `options` (JSON) |
| `phil_commerce_product_design` | `ProductDesign` | `code` (key), `definition` (JSON), `options` (JSON), `print_layout` (JSON, `[]` by default) |
| `phil_commerce_product_group` | `ProductGroup` | `id` (key), `design`, `template` (unique together), `active` |
| `phil_commerce_product_group_member` | `GroupMember` | `sku` (key), `group_id` (indexed), `axis_values` (JSON), `language`, `active`, `mockups` (JSON) |

`definition` holds the arrays of `TemplateArray` and `DesignArray`, with their `version`; reading a definition checks
every value, so a row edited by hand into something no code wrote is refused with `InvalidDefinition`. `options`
holds the arrays of `TemplateOption::toArray()`:

```json
[{"code": "ink", "label": "Ink", "kind": "ink", "required": false, "supplement": null, "text": null,
  "choices": [{"code": "glow", "label": "Glow in the dark", "image": null, "supplement": {"amount": 300, "currency": "CAD"}}],
  "drawer": null, "when": {"colour": ["BK", "NV"]}}]
```

`print_layout` holds the arrays of `PrintElement::toArray()`, in the order they are drawn; reading one checks every
value through the element's own constructors, and refuses with `TemplateRefused::unreadable()` what no code wrote:

```json
[{"kind": "dice", "face": "front", "zone": "chest", "option": "loot", "box": null, "path": null, "images": [],
  "slots": [{"box": {"x": 0.0, "y": 270.0, "width": 40.0, "height": 40.0}, "sides": 6, "images": ["loot/table-1/1.png", "..."]}],
  "font": null, "colour": "#000000", "max_size": 72.0, "min_size": 6.0, "ink": "colour", "when": {"version": ["L"]}, "chosen": {}}]
```

Print files and production orders are files of the production store, not rows ([Print files and production
orders](production.md)).

The products themselves are rows of the catalogue's `phil_commerce_catalog_product`, by SKU, like any other product.
A member is found by its SKU — the key —, and the members of a group by the index on `group_id`.
