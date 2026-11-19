# Changelog

All notable changes to `phil/taxonomy-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-18

The next version is 0.2.0-alpha: the keys of the tags, categories and assignments compare byte by byte on MySQL and
MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id` and `slug` of `phil_taxonomy_tag`, `id`, `category_key`, `parent` and `path` of
  `phil_taxonomy_category`, `id`, `category` and `locale` of `phil_taxonomy_category_translation`, and `id`,
  `term_type`, `term_id`, `resource_kind` and `resource_id` of `phil_taxonomy_assignment` take the `utf8mb4` character
  set and the `utf8mb4_bin` collation in the schema the ORM generates, through
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and SQLite keep their default, which compares bytes already.
- `doctrine/dbal` is required, already required by `doctrine/orm`: `BinaryKeysListener` reads the platform of the
  connection.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the tag `Doc-A` was found for `doc-a` and the
  unique slug refused the second, and the terms of the resource `Doc-A` were listed for `doc-a`.

## 0.1.0-alpha - 2026-11-11

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
