# Testing

## The bundle's tests

`tests/Unit` covers each part without a kernel: `CaseSelection` and `EnumTranslation` directly; the translatable
trait and `TranslatableText` with Symfony's `Translator` and in-memory catalogues; the form extension through a form
factory built with it; the validator with Symfony's `ConstraintValidatorTestCase`; the Twig extension directly and
in a template rendered by a Twig environment; the JSON Schema and the TypeScript export directly; the finder on the
files of `tests/Unit/Finder` and `tests/Unit/Pair`; the extractor on a `MessageCatalogue`; the commands with
Symfony's `CommandTester`; the normalizer inside a `Serializer`; the column type against a DBAL platform; the set
form type through a form factory; and the bundle class — configuration, services and the types it registers when
it boots — on a `ContainerBuilder`. The fixtures under `tests/Unit/Fixture` are enums with labels, descriptions,
groups, empty metadata, a name outside ASCII, bit flags, a default, a colour and an icon, and a deprecated case.

`tests/Functional` runs the bundle in a kernel of its own — FrameworkBundle with the translator, the serializer, the
validator and a workflow; DoctrineBundle on SQLite; TwigBundle —: the badge template found under `@PhilEnums`, a set
stored as a bitmask and read back, `phil:enums:check-sets` on a bad row, the workflow built from the transitions, a
route read with `#[ParseEnum]` and another guarded by `enum_in_group()`, the serializer and the validator, the
commands on the enums of the application's `src/`, and `translation:extract`. A second kernel registers API
Platform, NelmioApiDoc and MakerBundle: the schema of a resource's enum properties, the enum components of the
NelmioApiDoc document, and `make:enum` writing into the application.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/enums-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Every mutant of `src/` is killed by these tests.

## Testing your enums and forms

- A translatable enum: translate each case with a `Translator` holding your catalogue, and assert the generated key
  of a case without a label.
- A form field with a subset: build the form and read the values of `createView()->vars['choices']`.
- A constraint: extend `ConstraintValidatorTestCase` with `EnumCaseValidator`, as the bundle's own test does.
- An `EnumSet` column: convert a set to the database value and back with `EnumSetType` and any DBAL platform.
- Labels and translations: run `debug:enums <Enum> --locale=<locale>` in continuous integration; it fails on a
  missing required meta property or a missing translation.

Installed on its own, the functional suite is skipped: with `symfony/event-dispatcher` required for development
only, Symfony's container leaves the event dispatcher out and no kernel boots. It runs from an application that
requires an HTTP kernel, such as the monorepo root.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/enums-bundle/tests/Benchmark
```
