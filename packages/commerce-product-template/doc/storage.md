# Storage

`TemplateArray` and `DesignArray` write a template and a design as arrays of scalars, for a JSON column or a file,
and read them back:

```php
$json = json_encode(TemplateArray::toArray($template), JSON_THROW_ON_ERROR);
$template = TemplateArray::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
```

## The arrays

Each array has a `version`, 1 (`TemplateArray::VERSION`, `DesignArray::VERSION`). A template holds its `code`, `name`
by locale, `sku_pattern`, `supplier`, `pricing` — its `currency`, its `base` and its `prices` and `supplements` in minor
units by axis then value —, its `axes` with their values, its `faces` with their zones in millimetres and their
`photo_box`, and its `exclusions`. A design holds its `code`, `name`, `languages`, `artworks`, `templates` and
`allowed` values.

```json
{"version": 1, "code": "unisex", "name": {"en": "Unisex shirt"}, "sku_pattern": "{design}{size}{colour}",
 "supplier": null, "pricing": {"currency": "CAD", "base": 2500, "prices": {"size": {"2X": 2800}}, "supplements": {}},
 "axes": [{"code": "size", "label": {"en": "Size"}, "values": [{"code": "M", "label": {"en": "M"}, "swatch": null,
   "supplier_name": null, "photos": {}, "channels": []}]}], "faces": [], "exclusions": []}
```

## Reading back

`fromArray()` reads every value with the type it expects and builds the template or design through its constructors,
so an array read back is checked as one built in code. An array of another version, a value of the wrong type — named
by its path, `template.axes[0].values[2].code` — or a definition the constructors refuse throws `InvalidDefinition`.

Value codes of digits only, such as a size `0`, are keys of integers once PHP decodes them; the readers accept them as
the codes they are.
