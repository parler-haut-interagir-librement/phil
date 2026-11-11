# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later, `ext-mbstring` | The generated translation keys lower-case case names that may hold letters outside ASCII. |
| `phil/enums` | The metadata (`Label`, `Description`, `Group`), the metadata reader and `CaseCollection`. |
| `symfony/form`, `symfony/options-resolver` | The `EnumType` extension. |
| `symfony/validator` | The `EnumCase` constraint. |
| `symfony/translation-contracts` | `TranslatableInterface`, which translatable enums implement. |
| `twig/twig` | The Twig functions and filters. |
| `symfony/dependency-injection`, `symfony/config` | The bundle class and its configuration. |
| `ext-tokenizer` | The finder of the commands and of the translation extractor reads PHP files by their tokens. |

Several packages are optional: the bundle registers what needs them only when they are installed, and suggests
them.

| Optional package | Enables |
|---|---|
| `symfony/translation` | The translation extractor, and the translation check of `debug:enums` ([Translation](translation.md)). |
| `symfony/console` | The commands `debug:enums` and `phil:enums:typescript` ([Commands](commands.md)). |
| `symfony/serializer` | The normalizer of enum sets, enum maps and labelled cases ([APIs](api.md)). |
| `doctrine/dbal` | The `EnumSet` column types ([Doctrine](doctrine.md)). |
| `doctrine/orm` | The `phil:enums:check-sets` command. |
| `symfony/http-kernel` | The `#[ParseEnum]` controller argument resolver. |
| `symfony/expression-language` | The expression functions. |
| `api-platform/symfony` | The enum schemas of API Platform resources ([APIs](api.md)). |
| `nelmio/api-doc-bundle` | The enum schemas of NelmioApiDoc models ([APIs](api.md)). |
| `symfony/maker-bundle` | `make:enum` ([Commands](commands.md)). |
| `symfony/workflow` | The state machines built from `#[TransitionsTo]` ([Routing, expressions and workflows](routing-and-workflows.md)). |

The application registers FrameworkBundle, and TwigBundle for the Twig part: the bundle tags its services for them
(`form.type_extension`, `validator.constraint_validator`, `twig.attribute_extension`, `twig.runtime`) and uses the
`translator` service when there is one.

## Installing

```sh
composer require phil/enums-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\EnumsBundle\PhilEnumsBundle::class => ['all' => true],
];
```

Its configuration is optional: see [Configuration](configuration.md).
