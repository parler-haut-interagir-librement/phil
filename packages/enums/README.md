# phil/enums

**Traits and attributes that make PHP enums easier to list, compare, describe and group into sets.**

phil/enums adds what native enums leave out. It reads the cases as names, values or options, and counts, filters,
groups, sorts and draws them. It compares cases and walks them in order, finds a case by name or parses it
leniently, and tells whether the enum is pure or backed. Attributes attach metadata to cases, such as a label, a
description, a group or a weight, and `EnumSet` and `EnumMap` hold cases in sets and maps.

It is for PHP developers whose enums carry more than a list of cases. It does not validate input, map enums to a
database or a form, or translate labels: it only makes the enum itself more useful. In a Symfony application,
`phil/enums-bundle` adds translation, forms, validation, Twig, Doctrine and API contracts on top of it.

It is a plain PHP library with no package dependency, usable in any code. Each trait adds public methods to the
enum's API, so an enum uses only the traits it needs. PHPStan extensions type the methods the traits add and
check required metadata.

## Status

| Part | State |
|---|---|
| Traits: invocable, nameable, valuable, selectable, string-selectable, comparable, fromamble, metadatable, self-awareable, collectable, enumerable | Implemented and tested |
| Metadata: `Meta`, `Description`, `Group`, `Label`, `AbstractMetaProperty`, meta properties declared by traits | Implemented and tested |
| PHPStan extensions: static case calls, metadata accessors | Implemented |
| Operations on the cases: `CaseCollection` — count, first and last, filter, reject, only and except, `whereMeta()`, sorts, map | Implemented and tested |
| Metadata read once per enum and per case, kept for the process | Implemented and tested |
| Grouping, partition, `keyBy()`, `pluck()`, weighted `random()` | Implemented and tested |
| `EnumSet` (set operations, bitmasks) and total `EnumMap` | Implemented and tested |
| Ordinals, `parse()` with `#[Alias]`, `#[DefaultCase]`, deprecated cases | Implemented and tested |
| `#[TransitionsTo]` and `AsStatefulEnumTrait` | Implemented and tested |
| `SortDirection`, shared by sorts, queries and SQL | Implemented and tested |
| `EnumSet::fromValues()`, `EnumMap::fromPartial()`, `#[BitFlags]` | Implemented and tested |
| `#[RequiredMeta]`: PHPStan rule and run-time check | Implemented and tested |
| PHPStan rules of enum declarations and stored ordinals | Implemented, checked by the analysis of the fixtures |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the aggregate trait, and when to prefer single traits. |
| [Values, names and options](doc/values-and-names.md) | `__invoke()` and static case calls, `names()`, `values()`, `options()` and `stringOptions()`. |
| [Operations](doc/operations.md) | Counting, first and last, filtering by callback or meta property, grouping, sorting, random draws, and the immutable `CaseCollection`. |
| [Sets and maps](doc/sets-and-maps.md) | `EnumSet`, its set operations and bitmasks; the total `EnumMap`. |
| [Order, parsing, defaults and transitions](doc/order-and-parsing.md) | Ordinals, `parse()` and `#[Alias]`, `#[DefaultCase]`, `#[TransitionsTo]`, deprecated cases. |
| [Comparison](doc/comparison.md) | `is()`, `isNot()`, `in()`, `notIn()`, `has()`, `doesntHave()`, `equalsOneOf()`, `notEqualsOneOf()`. |
| [Lookup by name](doc/lookup-by-name.md) | `from()` and `tryFrom()` for pure enums, `fromName()` and `tryFromName()` for every enum. |
| [Metadata](doc/metadata.md) | Attaching values to cases, writing meta properties, accessors, `fromMeta()`, traits that declare meta properties, the shipped properties, required metadata. |
| [Self-awareness](doc/self-awareness.md) | `isPure()`, `isBacked()`, `isBackedByInteger()`, `isBackedByString()`. |
| [PHPStan](doc/phpstan.md) | Including the extensions, what they type, and the rules: required metadata, declarations, stored ordinals. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test your enums. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/enums
```

```php
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\Enums\Traits\AsValuableEnumTrait;

#[Meta(Label::class)]
enum Status: string
{
    use AsMetadatableEnumTrait;
    use AsValuableEnumTrait;

    #[Label('Draft')]
    case DRAFT = 'draft';

    #[Label('Published')]
    case PUBLISHED = 'published';
}

Status::values();           // ['draft', 'published']
Status::PUBLISHED->label(); // 'Published'
```

## Upgrading from ph-il/enums

This package was published as `ph-il/enums` up to 0.2.2, and is `phil/enums` from 0.3.0 on; `ph-il/enums` is
abandoned in its favour. The namespace stays `Phil\Enums`: replace the requirement with `composer remove ph-il/enums`
then `composer require phil/enums`, and read the [changelog](CHANGELOG.md) for the changes of 0.3.0.

## Requirements

PHP 8.5 or later and `ext-mbstring`; PHPStan 2 for the extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
