# Changelog

All notable changes to `phil/enums` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0 - 2026-11-11

### Added

- `Sort\SortDirection`: `asc` or `desc`, parsed leniently, with `sql()`, `compare()`, `reverse()`,
  `isDescending()` and `descendingIf()`; `sortBy()`, `sortByName()` and `sortByValue()` take it as well as a
  boolean.
- Operations on the cases: `AsCollectableEnumTrait` (`collect()`, `count()`, `first()`, `last()`, `filter()`,
  `whereMeta()`, `sortBy()`) and the immutable `Collection\CaseCollection`, which adds `reject()`, `only()`,
  `except()`, `contains()`, `sortByName()`, `sortByValue()`, `reverse()`, `map()`, `names()`, `values()`,
  `options()` and `isBacked()`. `AsEnumerableEnumTrait` includes the new trait.
- `inGroup()` on the enum and on `CaseCollection`: the cases in at least one of the given groups, read from every
  `#[Group]` a case carries; `Reflection::metaValueList()` gives every value of a repeatable meta property.
- The metadata reader keeps the meta properties of each enum and the values of each case after the first reading,
  and instantiates only meta property attributes.
- The PHPStan extension reads the meta properties a trait declares with its own `#[Meta]`, as the runtime
  already did.
- `groupBy()`, `groupByMeta()`, `partition()`, `keyBy()` and `pluck()` on `CaseCollection`; `groupBy()` and
  `groupByMeta()` on the enum.
- `random()`, weighted by the new `#[Weight]` meta property, with an optional `Random\Randomizer`.
- `Collection\EnumSet`: an immutable set of cases with union, intersection, difference, complement, inclusion and
  bitmasks for enums of bit flags.
- `Collection\EnumMap`: an immutable map that holds a value for every case, built by `total()` or `fromArray()`.
- `AsOrdinalEnumTrait`: `ordinal()`, `next()`, `previous()`, `compareTo()`, `isBefore()`, `isAfter()`,
  `isBetween()`.
- `AsParsableEnumTrait`: `parse()` and `tryParse()` by value, name or the new repeatable `#[Alias]`, optionally
  ignoring letter case.
- `AsDefaultableEnumTrait` and `#[DefaultCase]`: `default()` and `tryDefault()`.
- Deprecated cases: `isDeprecated()`, `withoutDeprecated()` and `deprecated()`, read from PHP's `#[\Deprecated]`.
- `#[RequiredMeta]`, a PHPStan rule (`philEnums.requiredMeta`) that reports a case missing a required meta
  property, and `Reflection::missingRequiredMeta()`.
- `AsStatefulEnumTrait` and `#[TransitionsTo]`: `canTransitionTo()`, `nextStates()`, `isFinal()`,
  `initialStates()`, `transitions()`.
- `EnumSet::fromValues()`, `EnumMap::fromPartial()`, and `#[BitFlags]`.
- `Reflection::parse()`: the lenient reading of `parse()` for any enum.
- PHPStan rules: `philEnums.defaultCase`, `philEnums.ambiguousAlias`, `philEnums.negativeWeight`,
  `philEnums.notBitFlags`, `philEnums.requiredMetaUndeclared` and `philEnums.storedOrdinal`.
- `AsEnumerableEnumTrait` includes the ordinal, parsable, defaultable and stateful traits.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `Reflection::metaPropertyNamed()`: the meta property an accessor reads.

### Changed

- The package is `phil/enums`, renamed from `ph-il/enums`, which is abandoned in its favour; the namespace stays
  `Phil\Enums`.
- PHP 8.5 or later, and `ext-mbstring` declared.
- `Meta\Reflection` is final and its attribute parser private; the traits read their cases through
  `Internal\Cases`.
- The meta property base, the invocable, fromamble, comparable and metadatable traits, the string options, the
  `#[Meta]` attribute, `UndefinedCaseError`, the reading of enabled meta properties and the two PHPStan extensions are
  written from scratch, with the same public API; the extensions share `PHPStan\VirtualMethod`.
- `AbstractMetaProperty::make()` is static, so `Label::make('Draft')` builds the value `fromMeta()` looks for; a call
  on an instance still works.
- An accessor no meta property of the enum names throws `BadMethodCallException` instead of returning `null`.
- `Reflection::metaProperties()` lists each accessor once: of two enabled properties with the same accessor, the
  first listed.

### Fixed

- `values()`, `options()`, `names()` and the invocable accessors are typed without inline `@var` overrides.
- `stringOptions()` escapes the value and the label of its default options for HTML, joins them with a new line
  (its default glue was a backslash and an `n`), and writes out a pure enum by name instead of returning an empty
  string.
- The enum's `sortBy()` takes a `SortDirection`, as `CaseCollection::sortBy()` does.

## 0.2.2 - 2026-09-13

### Added

- `AbstractMetaProperty` is now generic over its value type (`@template TValue`). Meta properties declare
  what they hold with `@extends AbstractMetaProperty<string>` (done for Description, Group and Label)

### Changed

- The PHPStan extension now infers the real return type of the generated meta accessors
  (`->label()`, `->description()`, ...) from the meta property's `TValue`, instead of always reporting `mixed`
- An accessor is only nullable when at least one case of the enum lacks the attribute and can therefore
  fall back to `defaultValue()`; an enum that attributes every case gets the bare value type
- `Meta`, `Meta\Reflection` and `AsMetadatableEnumTrait` signatures updated for the generic `AbstractMetaProperty`

## 0.2.1 - 2026-04-15

### Fixed

- Add Attribute::TARGET_CLASS_CONSTANT to the Meta Attributes (Description, Group, Label)
- Fix link in documentation

## 0.2.0 - 2026-03-01

### Added

- Label Meta Property
- Add equalsOneOf and notEqualsOneOf in AsComparableEnumTrait
- Add AsSelfAwareableEnumTrait : self-awareness 
  * isPure
  * isBacked
  * isBackedByInteger
  * isBackedByString
- Add PHPStan Extensions
- Add Tests
- Add Castor as Runner

### Changed

- Documentation

### Fixed

- Documentation
- All PHPStan Errors
- Fixes all CS problems

## 0.1.0 - 2026-02-19

### Added

- First implementation of the package
- AsComparableEnumTrait
- AsFromambleEnumTrait
- AsInvocableEnumTrait
- AsMetadableEnumTrait
- AsNameableEnumTrait
- AsValuableEnumTrait
- AsSelectableEnumTrait
- AsStringSelectableEnumTrait
- AsEnumerableEnumTrait
- Meta
  - Description
  - Group
