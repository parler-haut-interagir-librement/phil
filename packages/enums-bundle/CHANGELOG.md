# Changelog

All notable changes to `phil/enums-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `CaseSelection`: subsets by `only`, `except`, groups and meta values.
- Translation: `AsTranslatableEnumTrait`, `Translatable`, `TranslatableText`, keys from `Label` and `Description`;
  `EnumTranslation` reads the `Translatable` attribute of an enum once per process, and builds the generated label
  key of a case once; a translation extractor for `translation:extract`.
- Doctrine: `EnumSet` column types, bitmask or JSON.
- Forms: `EnumType` options `group`, `only`, `except`, `where_meta`, `without_deprecated`, `group_by_meta`,
  `preferred_group`, labels from metadata; `EnumSetType`.
- Validation: the `EnumCase` constraint, which reads an integer written as a string for an enum backed by integers
  and finds no case, without an error, in a value of another type; the `EnumSetOf` constraint; the `Transition`
  constraint, from the `#[TransitionsTo]` of the previous case, read from the object or from Doctrine.
- Twig: `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge` with `Color` and `Icon`.
- Commands: `debug:enums`; `phil:enums:typescript`, with the descriptions, groups and default, and `--check`;
  `phil:enums:icu`, which refuses, naming the case, a key ICU does not accept as a keyword (white space or
  punctuation such as `-` or `.`), a key `other`, a key that comes twice and an argument name ICU does not accept;
  `phil:enums:check-sets`, which also runs when Doctrine ORM is installed without DoctrineBundle; `make:enum`, which
  renders the enum with `EnumBlueprint::source()`, its template only printing it, and refuses two names that give a
  string enum the same value, and a name ending in a new line.
- `EnumSchema` (JSON Schema) and `EnumsNormalizer` (serializer), with enum sets and maps read back in the serializer;
  the schemas of enums in API Platform (property metadata) and NelmioApiDoc (model describer).
- `#[ParseEnum]` controller arguments, the `enum_meta`, `enum_in_group` and `enum_from` expression functions, and
  Symfony state machines built from `#[TransitionsTo]` (`workflows` configuration).
- A functional suite that runs every integration in a kernel.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
