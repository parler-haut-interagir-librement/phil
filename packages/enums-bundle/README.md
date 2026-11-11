# phil/enums-bundle

**Your PHP enums in Symfony forms, translations, Twig, validation, Doctrine and API contracts.**

phil/enums-bundle is the Symfony integration of `phil/enums`. It translates enum cases by their `Label` and
`Description` metadata. Form fields offer only a subset of an enum (a group, a list, the cases with a given meta
value) or hold `EnumSet` data, and a validator constraint accepts exactly that subset. Twig gets functions, filters
and badges. A translation extractor, a `debug:enums` command and a TypeScript export serve the developer. JSON
Schema, a serializer normalizer and Doctrine column types for enum sets serve API contracts and storage.

It is for Symfony applications whose enums reach the screen, the database or an API. It does not map a single case
to the database, since Doctrine's `enumType` does. It does not read cases from a request either: Symfony's argument
resolver does, and this bundle's `#[ParseEnum]` does it more leniently. Outside Symfony, `phil/enums` alone gives
the traits and attributes.

What varies per enum is declared on the enum itself, with the attributes of `phil/enums`. The configuration only
says where the commands look, which set columns exist and which enums are workflows. Optional integrations wake up
when their package is installed: the console, the serializer, the expression language, Symfony Workflow, API
Platform, NelmioApiDoc and the maker.

## Status

| Part | State |
|---|---|
| `CaseSelection`: subsets by `only`, `except`, groups and meta values | Implemented and tested |
| Translation: `AsTranslatableEnumTrait`, `Translatable`, `TranslatableText`, keys from `Label` and `Description` | Implemented and tested |
| Forms: `EnumType` options `group`, `only`, `except`, `where_meta`, labels from metadata | Implemented and tested |
| Validation: the `EnumCase` constraint | Implemented and tested |
| Twig: `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge` with `Color` and `Icon` | Implemented and tested |
| Forms: `without_deprecated`, `group_by_meta`, `preferred_group`; `EnumSetType` | Implemented and tested |
| Translation extractor for `translation:extract` | Implemented and tested |
| Commands: `debug:enums`, `phil:enums:typescript` | Implemented and tested |
| `EnumSchema` (JSON Schema) and `EnumsNormalizer` (serializer) | Implemented and tested |
| Doctrine: `EnumSet` column types, bitmask or JSON; `phil:enums:check-sets` | Implemented and tested |
| Validation: `EnumSetOf`; serializer reads sets and maps back | Implemented and tested |
| Validation: `Transition`, a change of state the enum declares | Implemented and tested |
| `#[ParseEnum]` controller arguments, expression functions, workflows from `#[TransitionsTo]` | Implemented and tested |
| TypeScript export with descriptions, groups, default and `--check`; `phil:enums:icu` | Implemented and tested |
| Enum schemas in API Platform and NelmioApiDoc; `make:enum` | Implemented and tested |
| A kernel test of every integration | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the bundle. |
| [Configuration](doc/configuration.md) | The three optional keys, and the services the bundle registers with their tags. |
| [Selecting cases](doc/selection.md) | `CaseSelection`: the four criteria every part shares, and groups as named subsets. |
| [Translation](doc/translation.md) | Translatable enums, the label and description keys, the domain and prefix, and extracting the keys. |
| [Forms](doc/forms.md) | The `EnumType` options, the label of a choice, and `EnumSetType`. |
| [Validation](doc/validation.md) | The `EnumCase` and `EnumSetOf` constraints, what they read, and their error codes. |
| [Twig](doc/twig.md) | The functions and filters, and badges. |
| [Commands](doc/commands.md) | `debug:enums`, `phil:enums:typescript`, `phil:enums:icu`, `phil:enums:check-sets` and `make:enum`. |
| [APIs](doc/api.md) | The serializer's normalizer, reading sets and maps back, the JSON Schema of an enum, API Platform and NelmioApiDoc. |
| [Doctrine](doc/doctrine.md) | Storing an `EnumSet` in a column, as a bitmask or a JSON list, and checking the stored values. |
| [Routing, expressions and workflows](doc/routing-and-workflows.md) | `#[ParseEnum]`, the expression functions, and state machines from `#[TransitionsTo]`. |
| [Extension points](doc/extension-points.md) | What an application declares on its enums, forms and properties. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test your enums, forms and constraints. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/enums-bundle
```

```php
#[Meta(Label::class, Group::class)]
enum OrderStatus: string implements TranslatableInterface
{
    use AsMetadatableEnumTrait;
    use AsTranslatableEnumTrait;

    #[Label('Pending')] #[Group('customer')]
    case PENDING = 'pending';

    #[Label('Cancelled')]
    case CANCELLED = 'cancelled';
}

// A field that offers the customer's statuses, labelled in the user's language
$builder->add('status', EnumType::class, ['class' => OrderStatus::class, 'group' => 'customer']);

// The constraint that accepts the same subset
#[EnumCase(OrderStatus::class, inGroup: 'customer')]
public ?OrderStatus $status = null;
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-tokenizer`, `phil/enums`, and Symfony 8.2's form, validator and
translation contracts; Twig 3.21 or later for the Twig part. Optional: `symfony/translation`, `symfony/console`,
`symfony/serializer`, `doctrine/dbal`, `doctrine/orm`, `symfony/http-kernel`, `symfony/expression-language` and
`symfony/workflow`, API Platform, NelmioApiDoc and MakerBundle, for the parts that need them.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
