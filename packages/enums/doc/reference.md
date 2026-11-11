# Reference

| Type | Role |
|---|---|
| `Traits\AsInvocableEnumTrait` | `__invoke()` and static case calls, both returning the case's primitive. |
| `Traits\AsNameableEnumTrait` | `names()`: the case names. |
| `Traits\AsValuableEnumTrait` | `values()`: the case values, or the names of a pure enum. |
| `Traits\AsSelectableEnumTrait` | `options()`: name to value for a backed enum, the names for a pure one. |
| `Traits\AsStringSelectableEnumTrait` | `stringOptions()`: the options formatted by a callback and joined by a glue, HTML `<option>` elements by default, escaped and one per line. |
| `Traits\AsComparableEnumTrait` | `is()`, `isNot()`, `in()`, `notIn()`, `has()`, `doesntHave()`, `equalsOneOf()`, `notEqualsOneOf()`. |
| `Traits\AsFromambleEnumTrait` | `from()` and `tryFrom()` by name for pure enums, `fromName()` and `tryFromName()` for every enum. |
| `Traits\AsMetadatableEnumTrait` | Metadata accessors, `fromMeta()` and `tryFromMeta()`; an accessor no enabled meta property names throws `BadMethodCallException`. |
| `Traits\AsSelfAwareableEnumTrait` | `isPure()`, `isBacked()`, `isBackedByInteger()`, `isBackedByString()`. |
| `Traits\AsCollectableEnumTrait` | `collect()`, `count()`, `first()`, `last()`, `filter()`, `whereMeta()`, `inGroup()`, `sortBy()` on the enum. |
| `Traits\AsOrdinalEnumTrait` | `ordinal()`, `next()`, `previous()`, `compareTo()`, `isBefore()`, `isAfter()`, `isBetween()` ([Order, parsing and defaults](order-and-parsing.md)). |
| `Traits\AsParsableEnumTrait` | `parse()` and `tryParse()`: by value, name or alias, with or without regard to letter case. |
| `Traits\AsDefaultableEnumTrait` | `default()` and `tryDefault()`: the case marked `#[DefaultCase]`. |
| `Traits\AsStatefulEnumTrait` | `canTransitionTo()`, `nextStates()`, `isFinal()`, `initialStates()`, `transitions()`: the moves `#[TransitionsTo]` declares. |
| `Traits\AsEnumerableEnumTrait` | Every trait except `AsStringSelectableEnumTrait`. |
| `Sort\SortDirection` | `asc` or `desc`, parsed leniently; its SQL keyword, its comparison, its reverse ([Operations](operations.md)). |
| `Collection\CaseCollection` | An immutable list of cases of one enum, with the operations ([Operations](operations.md)). |
| `Collection\EnumSet` | An immutable set of cases of one enum, with set operations and bitmasks ([Sets and maps](sets-and-maps.md)). |
| `Collection\EnumMap` | An immutable map holding a value for every case of one enum ([Sets and maps](sets-and-maps.md)). |
| `Attribute\Meta` | The meta properties an enum, or a trait, declares. |
| `Attribute\Description`, `Attribute\Group`, `Attribute\Label` | Meta properties of string value, shipped with the library. |
| `Attribute\Alias` | A repeatable meta property: another spelling `parse()` accepts. |
| `Attribute\Weight` | A meta property: how often `random()` draws the case, 1 by default. |
| `Attribute\DefaultCase` | Marks the case `default()` returns. |
| `Attribute\TransitionsTo` | The cases a case may move to. |
| `Attribute\BitFlags` | Declares an enum of bit flags, checked by PHPStan. |
| `Attribute\RequiredMeta` | The meta properties every case must carry, checked by PHPStan and `Reflection::missingRequiredMeta()`. |
| `Meta\AbstractMetaProperty` | The base of a meta property: its value, `transform()`, `defaultValue()`, `customMethodName()`, `method()` and the static `make()`. |
| `Meta\Reflection` | Reads the meta properties of an enum, and the value — or every value, `metaValueList()` — of one on a case, once per enum and per case; the meta property an accessor reads, `metaPropertyNamed()`; whether a case is deprecated, the default case, the required metadata cases miss, a case's transitions, and `parse()` for any enum. |
| `Exceptions\UndefinedCaseError` | A static call names no case of the enum. |
| `Internal\Cases` | Internal: what the traits read from the cases — names, primitives, options — and the check that cases belong to an enum. |
| `PHPStan\InvocableEnum\ReflectionExtension`, `PHPStan\MetadatableEnum\ReflectionExtension` | The PHPStan extensions ([PHPStan](phpstan.md)). |
| `PHPStan\VirtualMethod` | How both extensions describe to PHPStan a method a trait answers through `__call()` or `__callStatic()`. |
| `PHPStan\RequiredMeta\RequiredMetaRule` | The PHPStan rule of `#[RequiredMeta]`. |
| `PHPStan\Declaration\EnumDeclarationRule` | The PHPStan rule of what an enum's attributes declare wrongly. |
| `PHPStan\Ordinal\StoredOrdinalRule` | The PHPStan rule of an ordinal that leaves the process. |
