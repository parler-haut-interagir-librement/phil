# Reference

| Type | Role |
|---|---|
| `PhilEnumsBundle` | The bundle class: its configuration, the services, and the `EnumSet` column types it registers when it boots. |
| `Application\Selection\CaseSelection` | A subset of an enum's cases by `only`, `except`, `groups` and `whereMeta`; `fromOptions()` reads it from loosely typed options. |
| `Application\Translation\EnumTranslation` | The label key, the description key, the domain, and the plain label of a case. |
| `Application\Translation\Translatable` | The attribute that sets an enum's translation domain and key prefix. |
| `Application\Presentation\Color`, `Application\Presentation\Icon` | Meta properties of string value: the colour and icon of a badge, as names. |
| `Application\Schema\EnumSchema` | The JSON Schema of an enum, for an OpenAPI document. |
| `Application\TypeScript\TypeScriptExporter` | Enums written as TypeScript: constant objects, union types and labels. |
| `Infrastructure\Discovery\EnumFinder` | The enums declared in PHP files that the autoloader finds. |
| `Infrastructure\Translation\EnumTranslationExtractor` | The keys of translatable enums, for `translation:extract`. |
| `Infrastructure\Console\DebugEnumsCommand` | `debug:enums`. |
| `Infrastructure\Console\ExportTypeScriptCommand` | `phil:enums:typescript`. |
| `Infrastructure\Serializer\EnumsNormalizer` | Writes enum sets, enum maps, and cases with their labels; reads sets and maps back. |
| `Infrastructure\Doctrine\EnumSetType`, `Infrastructure\Doctrine\EnumSetStorage` | The DBAL type of an `EnumSet` column, and its two storages. |
| `Infrastructure\Form\EnumSetType` | The form type of an `EnumSet`. |
| `Application\Maker\EnumBlueprint` | The cases `make:enum` writes, with their values and labels. |
| `Infrastructure\Maker\MakeEnum` | `make:enum`. |
| `Infrastructure\OpenApi\NelmioEnumModelDescriber` | The schema of an enum model in NelmioApiDoc. |
| `Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory` | The schema of an enum property in API Platform. |
| `Application\Icu\IcuSelect` | An ICU `select` message over the cases of an enum. |
| `Infrastructure\Validator\EnumSetOf`, `Infrastructure\Validator\EnumSetOfValidator` | The constraint of an `EnumSet`, and its validator. |
| `Infrastructure\Http\ParseEnum`, `Infrastructure\Http\ParsedEnumValueResolver` | The controller argument attribute, and its resolver. |
| `Infrastructure\ExpressionLanguage\EnumsExpressionProvider` | `enum_meta()`, `enum_in_group()`, `enum_from()`. |
| `Infrastructure\Workflow\EnumWorkflows` | The state machine configuration of an enum's transitions. |
| `Infrastructure\Console\ExportIcuCommand` | `phil:enums:icu`. |
| `Infrastructure\Console\CheckSetColumnsCommand` | `phil:enums:check-sets`. |
| `Infrastructure\Translation\AsTranslatableEnumTrait` | `trans()` and `translatableDescription()` for an enum that implements `TranslatableInterface`. |
| `Infrastructure\Translation\TranslatableText` | A key in a domain, translated when rendered. |
| `Infrastructure\Form\EnumTypeExtension` | The `EnumType` options `group`, `only`, `except`, `where_meta`, `without_deprecated`, `group_by_meta`, `preferred_group`, and the default choice label. |
| `Infrastructure\Validator\EnumCase` | The constraint: a value that is a case of a selection. |
| `Infrastructure\Validator\EnumCaseValidator` | Its validator. |
| `Infrastructure\Validator\Transition`, `Infrastructure\Validator\TransitionValidator` | The constraint of a change of state, and its validator. |
| `Infrastructure\Twig\EnumsExtension` | `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge`. |
