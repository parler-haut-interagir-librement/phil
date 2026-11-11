# Changelog

All notable changes to `phil/taxonomy-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- The entities `Tag`, a name of 1 to `Tag::MAX_LENGTH` (100) characters, `Category`, at depth 10 at most, `CategoryTranslation` and
  `Assignment`; the exceptions `TaxonomyException` and `InvalidTaxonomy`; the port `TaxonomyRepository`, which reads
  tags, categories and translations by lists in one query. Resources are `ResourceReference`s of `phil/resource`,
  whose `InvalidResource` a malformed reference throws.
- `Taxonomy` and `CategoryNode`: tag slugs and category keys are `Slug`s of `phil/slug`, made by a `Slugger` — the
  transliterating one at most 128 characters by default —; `Taxonomy::move()` refuses a branch whose deepest
  category would pass depth 10; `Taxonomy::names()`, `tree()`, `tagsOf()` and `categoriesOf()` read in a set number
  of queries, `names()` the translations of the language, its base language and the fallback language only;
  `Taxonomy::mergeCategories()` reads the tree once and writes the children in one flush; `Taxonomy::setTags()`
  refuses more than `Taxonomy::MAX_TAGS` (100) names with `InvalidTaxonomy`, before any query.
- `DoctrineTaxonomyRepository`, whose `translationsOf()` takes the locales to read.
- `PhilTaxonomyBundle`, extension alias `phil_taxonomy`, keys `fallback_locale` and `slugger`, the service id of the
  slugger of tag names and category keys.
- `TagsType`, whose `max_tags` option, 20 by default and at most `Taxonomy::MAX_TAGS`, caps the distinct tags, and `TagsTransformer`, which tells
  tags apart in linear time and fails with the `validators` messages `taxonomy.tags.too_many` and
  `taxonomy.tags.too_long` past the limit or past 100 characters; `CategoryType`, whose choices are the categories, labelled apart with the names
  their tree was read with, so that two categories of the same name at the same depth are both offered; labels in
  English and French.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
