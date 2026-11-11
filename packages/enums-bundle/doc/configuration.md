# Configuration

The alias is `phil_enums`. Three keys, all optional:

```yaml
# config/packages/phil_enums.yaml
phil_enums:
    # Where debug:enums and phil:enums:typescript look for enums: directories or files.
    paths: ['%kernel.project_dir%/src']

    doctrine:
        # DBAL types that store an EnumSet, by type name (see Doctrine).
        sets:
            permissions: { enum: App\Security\Permission, storage: bitmask }
            channels: { enum: App\Notification\Channel }   # storage: json, the default

    # Symfony state machines built from the #[TransitionsTo] of an enum, by workflow name.
    workflows:
        order: { enum: App\Order\OrderStatus, supports: [App\Order\Order], property: status }
```

| Key | Default | Meaning |
|---|---|---|
| `paths` | `['%kernel.project_dir%/src']` | The directories and files the commands read for enum declarations ([Commands](commands.md)). |
| `doctrine.sets.<name>.enum` | required | The enum of the set; a class that is not an enum fails the container build. |
| `doctrine.sets.<name>.storage` | `json` | `bitmask` — one integer, for an enum of bit flags — or `json` — a JSON list of values ([Doctrine](doctrine.md)). |
| `workflows.<name>.enum` | required | A backed enum whose cases declare `#[TransitionsTo]` ([Routing, expressions and workflows](routing-and-workflows.md)). |
| `workflows.<name>.supports` | required | The classes the workflow applies to; at least one. |
| `workflows.<name>.property` | `status` | The property that holds the case. |

What varies per enum — its translation domain and key prefix, its groups, its colour — is declared on the enum
itself, never here.

## Services

The bundle registers each service as soon as it is installed, or — for the integrations — as soon as the package
the service needs is. Each acts only on what asks for it.

| Service | Tags | Registered | Acts on |
|---|---|---|---|
| `Infrastructure\Form\EnumTypeExtension` | `form.type_extension` | Always | Every `EnumType` field; changes nothing without the options of [Forms](forms.md), except labels. |
| `Infrastructure\Form\EnumSetType` | `form.type` | Always | Fields of `EnumSet` data ([Forms](forms.md)). |
| `Infrastructure\Validator\EnumCaseValidator`, `Infrastructure\Validator\EnumSetOfValidator` | `validator.constraint_validator` | Always | The `EnumCase` and `EnumSetOf` constraints ([Validation](validation.md)). |
| `Infrastructure\Validator\TransitionValidator` | `validator.constraint_validator` | Always; given the `doctrine` service when there is one | The `Transition` constraint: it reads the previous case from Doctrine's unit of work, or from the object alone without Doctrine ([Validation](validation.md)). |
| `Infrastructure\Twig\EnumsExtension` | `twig.attribute_extension`, `twig.runtime` | Always | The Twig functions and filters ([Twig](twig.md)). |
| `Infrastructure\Translation\EnumTranslationExtractor` | `translation.extractor` (alias `phil_enums`) | With `symfony/translation` | `translation:extract` ([Translation](translation.md)). |
| `Infrastructure\Console\DebugEnumsCommand`, `Infrastructure\Console\ExportTypeScriptCommand`, `Infrastructure\Console\ExportIcuCommand` | `console.command` | With `symfony/console` | `debug:enums`, `phil:enums:typescript` and `phil:enums:icu` ([Commands](commands.md)). |
| `Infrastructure\Console\CheckSetColumnsCommand` | `console.command` | With `symfony/console` and `doctrine/orm` | `phil:enums:check-sets` ([Doctrine](doctrine.md)). |
| `Infrastructure\Http\ParsedEnumValueResolver` | `controller.argument_value_resolver` | With `symfony/http-kernel` | Arguments marked `#[ParseEnum]` ([Routing, expressions and workflows](routing-and-workflows.md)). |
| `Infrastructure\ExpressionLanguage\EnumsExpressionProvider` | `security.`, `routing.` and `validator.expression_language_provider` | With `symfony/expression-language` | `enum_meta()`, `enum_in_group()`, `enum_from()`. |
| `Infrastructure\OpenApi\NelmioEnumModelDescriber` | `nelmio_api_doc.model_describer`, before `nelmio_api_doc.model_describers.enum` | With `nelmio/api-doc-bundle` | Enum models ([APIs](api.md)). |
| `Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory` | decorates `api_platform.metadata.property.metadata_factory`, around its schema decorator and within its cache | With `api-platform/symfony` | Enum properties of resources ([APIs](api.md)). |
| `Infrastructure\Maker\MakeEnum` | `maker.command` | With `symfony/maker-bundle` | `make:enum` ([Commands](commands.md)). |
| `Infrastructure\Serializer\EnumsNormalizer` | `serializer.normalizer`, before Symfony's normalizers of translatable values, enum cases and objects | With `symfony/serializer` | Enum sets, enum maps, and labelled cases ([APIs](api.md)). |

Every service that shows a label receives the `translator` service, or nothing when there is none. The `EnumSet`
column types are not services: the bundle registers them in DBAL's type registry when it boots, with
`doctrine/dbal` installed ([Doctrine](doctrine.md)). The workflows are not services of the bundle either: it
prepends them to FrameworkBundle's `workflows`, with `symfony/workflow` installed.
