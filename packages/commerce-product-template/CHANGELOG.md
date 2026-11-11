# Changelog

All notable changes to `phil/commerce-product-template` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Label` and `Swatch`: texts by locale, read in a locale, its language or the first given; a hexadecimal colour or
  the photo of a sample.
- `AxisValue` and `Axis`: values in order with their SKU code, label, swatch, supplier name, photo per face and
  channels; up to 500 values an axis, `design`, `lang` and `template` reserved.
- `PrintZone`, `PhotoBox` and `Face`: print zones in millimetres with their resolution and their rectangle on the
  photos of the face, the first zone of a face its default.
- `Pricing`: a base price, a price per value of one axis and supplements on any axis, in one currency, never
  negative.
- `Exclusion` and `Combination`: the combinations a template does not make, by axis values; one value of each axis,
  matched against a condition.
- `SkuPattern`: placeholders and literals, each placeholder once, `{design}` required, rendered per product.
- `Template`: a blank with up to 8 axes and 10 000 combinations, its faces, pricing, exclusions, SKU pattern and
  supplier, every name it uses checked; its combinations in order, the last axis varying fastest.
- `LanguageVersion`, `Artwork` and `Design`: a design's code, name, up to 8 language versions, up to 200 artworks by
  face, zone, language and condition, the templates it is made on and the values it allows.
- `VariantPlanner`, `Variant` and `PlacedArtwork`: one product per language version and allowed combination, its
  SKU, price, channels, most specific artwork per zone, name, attributes and photos; SKUs a catalogue refuses, two
  products sharing a SKU, a zone the template lacks, two artworks as specific as each other and more than its limit
  of products refused.
- `TemplateArray` and `DesignArray`: templates and designs as versioned arrays of scalars, read back with every
  value checked.
- `ProductTemplateException`, implemented by `InvalidDefinition` and `PlanRefused`.
- The fuzz targets `sku-pattern` and `template-array`, and the benchmark `VariantPlannerBench`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
