<!-- phil-dev source-sha256=883e471ad5d1d02aef514036484f700edcf7d61b9fd1e0e028918a3334a5ab8f -->
# Référence

| Type | Rôle |
|---|---|
| `PhilEnumsBundle` | La classe du bundle : sa configuration, les services et les types de colonne `EnumSet` qu'il enregistre à son démarrage. |
| `Application\Selection\CaseSelection` | Un sous-ensemble des cas d'une enum selon `only`, `except`, `groups` et `whereMeta` ; `fromOptions()` le lit à partir d'options faiblement typées. |
| `Application\Translation\EnumTranslation` | La clé de libellé, la clé de description, le domaine et le libellé brut d'un cas. |
| `Application\Translation\Translatable` | L'attribut qui fixe le domaine de traduction et le préfixe de clé d'une enum. |
| `Application\Presentation\Color`, `Application\Presentation\Icon` | Des méta-propriétés à valeur de chaîne : la couleur et l'icône d'un badge, sous forme de noms. |
| `Application\Schema\EnumSchema` | Le JSON Schema d'une enum, pour un document OpenAPI. |
| `Application\TypeScript\TypeScriptExporter` | Des enums écrites en TypeScript : objets de constantes, types union et libellés. |
| `Infrastructure\Discovery\EnumFinder` | Les enums déclarées dans des fichiers PHP que l'autoloader trouve. |
| `Infrastructure\Translation\EnumTranslationExtractor` | Les clés des enums traduisibles, pour `translation:extract`. |
| `Infrastructure\Console\DebugEnumsCommand` | `debug:enums`. |
| `Infrastructure\Console\ExportTypeScriptCommand` | `phil:enums:typescript`. |
| `Infrastructure\Serializer\EnumsNormalizer` | Écrit les ensembles d'enums, les tables d'enums et les cas avec leurs libellés ; relit les ensembles et les tables. |
| `Infrastructure\Doctrine\EnumSetType`, `Infrastructure\Doctrine\EnumSetStorage` | Le type DBAL d'une colonne `EnumSet`, et ses deux stockages. |
| `Infrastructure\Form\EnumSetType` | Le type de formulaire d'un `EnumSet`. |
| `Application\Maker\EnumBlueprint` | Les cas qu'écrit `make:enum`, avec leurs valeurs et leurs libellés. |
| `Infrastructure\Maker\MakeEnum` | `make:enum`. |
| `Infrastructure\OpenApi\NelmioEnumModelDescriber` | Le schéma d'un modèle d'enum dans NelmioApiDoc. |
| `Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory` | Le schéma d'une propriété d'enum dans API Platform. |
| `Application\Icu\IcuSelect` | Un message ICU `select` sur les cas d'une enum. |
| `Infrastructure\Validator\EnumSetOf`, `Infrastructure\Validator\EnumSetOfValidator` | La contrainte d'un `EnumSet`, et son validateur. |
| `Infrastructure\Http\ParseEnum`, `Infrastructure\Http\ParsedEnumValueResolver` | L'attribut d'argument de contrôleur, et son résolveur. |
| `Infrastructure\ExpressionLanguage\EnumsExpressionProvider` | `enum_meta()`, `enum_in_group()`, `enum_from()`. |
| `Infrastructure\Workflow\EnumWorkflows` | La configuration de machine à états des transitions d'une enum. |
| `Infrastructure\Console\ExportIcuCommand` | `phil:enums:icu`. |
| `Infrastructure\Console\CheckSetColumnsCommand` | `phil:enums:check-sets`. |
| `Infrastructure\Translation\AsTranslatableEnumTrait` | `trans()` et `translatableDescription()` pour une enum qui implémente `TranslatableInterface`. |
| `Infrastructure\Translation\TranslatableText` | Une clé dans un domaine, traduite au rendu. |
| `Infrastructure\Form\EnumTypeExtension` | Les options `group`, `only`, `except`, `where_meta`, `without_deprecated`, `group_by_meta`, `preferred_group` de `EnumType`, et le libellé par défaut des choix. |
| `Infrastructure\Validator\EnumCase` | La contrainte : une valeur qui est un cas d'une sélection. |
| `Infrastructure\Validator\EnumCaseValidator` | Son validateur. |
| `Infrastructure\Validator\Transition`, `Infrastructure\Validator\TransitionValidator` | La contrainte d'un changement d'état, et son validateur. |
| `Infrastructure\Twig\EnumsExtension` | `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge`. |
