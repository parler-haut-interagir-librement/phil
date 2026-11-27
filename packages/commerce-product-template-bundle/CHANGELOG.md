# Changelog

All notable changes to `phil/commerce-product-template-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-26

### Changed

- Accepts `phil/ddd-bundle` 0.2 as well as 0.1 (`^0.1@dev || ^0.2@dev`), so that it installs beside the typed identifiers in
  controllers and the Serializer.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`doctrine/orm` 3.8), and of each development requirement
  (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2,
  `symfony/event-dispatcher-contracts` 3.8).

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

### Changed

- It requires `phil/commerce-bundle` 0.5, the minor released the same evening.

## 0.1.0-alpha - 2026-11-20

### Added

- `ProductTemplate` and `ProductDesign`: templates and designs stored with their definitions as versioned JSON, the
  kind and tax category of a template's products, the options of each, and the print layout of a design.
- `TemplateOption`: an option of the core of commerce with a condition on axis values, kept as an array of scalars and
  read back through the core's constructors; an amount option is refused, chosen on an order line, never in a
  template; each code once per owner.
- `ProductGroup` and `GroupMember`: the products of a design on a template as one group, each member by its SKU with
  its values, language version, state and mockups; withdrawn, never deleted.
- `CatalogGeneration`: the products of a plan created, updated, restored and withdrawn in the catalogue, every check
  before the first change; a SKU of another group, a product of the catalogue outside any group, a change of kind or
  currency refused. `GenerationReport`, by SKU.
- `ProductGroups` and `GroupPage`: the page of a group in a locale, a selector per axis with the values sold, and the
  SKU of the values chosen.
- `TemplateOptions`: the core's `SellableOptions` for the configured type, the template's options then the design's,
  a design's replacing a template's of the same code, each kept when its condition meets the product's values;
  supplements in another currency refused.
- `Mockups` and the port `ImageStore`: mockups composed by `phil/print-compositor` for each active member and face,
  named by a digest of their content, composed once per run and only when missing; `DirectoryImageStore`, paths
  checked, written whole.
- `PrintElement`, `DiceSlot` and `PrintElementKind`: the print layout of a design — fixed images, the text of a text
  option in its imposed font and rectangle, the image of the choice made, one object per die of a draw —, each with a
  condition on axis values and on the choices of the line, and an ink; kept as JSON in `print_layout`, read back
  checked, at most 64 elements.
- `DiceCode`: the results of dice read from a draw, as numbers or one character per die, and written one character
  per die, `1` to `9` then `A` to `K`.
- `PrintProduction`: the print files of an order composed by `phil/print-compositor`, one per zone and ink, named by
  their content and composed only when missing, and its production order written in JSON and CSV; units printed
  alike grouped; an order not placed or with draws to make refused before anything is written. `ProductionOrder`,
  `ProductionLine` and `PrintFile`; CSV cells a spreadsheet would read as a formula escaped.
- `ProducePrintFiles`, carrying the order's number only, its handler `ProducePrintFilesHandler`, retrying draws still
  to make and a store that cannot write and nothing else, and `ProduceOnPurchase`, which dispatches it on
  `OrderPurchased`.
- The ports `ProductionStore` and `PrintFonts`; `DirectoryProductionStore`, readable by its owner only, and
  `ConfiguredFonts`; `DirectoryImageStore` takes the modes of its directories and files.
- The Stimulus controller `variant-selector`: a product of a group chosen by a selector per axis, the values the group
  does not sell with the others disabled, its SKU and mockups shown, without a `style` attribute; served by
  AssetMapper.
- `TemplateRefused`, with its factories, and `isRetryable()` for an order whose draws are not made yet.
- `ProductTemplateRepository`, `ProductDesignRepository`, `ProductGroupRepository` and their Doctrine adapters, on
  `phil_commerce_product_template`, `phil_commerce_product_design`, `phil_commerce_product_group` and
  `phil_commerce_product_group_member`.
- `PhilCommerceProductTemplateBundle`, which refuses to load without the core and the catalogue, with the keys
  `sellable_type`, `default_locale`, `images_directory`, `mockups` and `production`.
- The Flex recipe in `recipe/0.1`: the bundle registered, its defaults needing no configuration file.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
