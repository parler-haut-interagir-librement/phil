<!-- phil-dev source-sha256=65a00bf01b1f83adf163a338f8a6b4dbd55f94c9829bc28e9bf13dc4e1c1ae3a -->
# Configuration

L'alias est `phil_enums`. Il y a trois clés, toutes facultatives :

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

| Clé | Valeur par défaut | Signification |
|---|---|---|
| `paths` | `['%kernel.project_dir%/src']` | Les répertoires et les fichiers que les commandes lisent pour trouver les déclarations d'enum ([Commandes](commands.md)). |
| `doctrine.sets.<name>.enum` | requise | L'enum de l'ensemble ; une classe qui n'est pas une enum fait échouer la construction du conteneur. |
| `doctrine.sets.<name>.storage` | `json` | `bitmask` (un entier, pour une enum d'indicateurs binaires) ou `json` (une liste JSON de valeurs) ([Doctrine](doctrine.md)). |
| `workflows.<name>.enum` | requise | Une enum à valeurs dont les cas déclarent `#[TransitionsTo]` ([Routage, expressions et workflows](routing-and-workflows.md)). |
| `workflows.<name>.supports` | requise | Les classes auxquelles le workflow s'applique ; au moins une. |
| `workflows.<name>.property` | `status` | La propriété qui contient le cas. |

Ce qui varie d'une enum à l'autre (son domaine de traduction et son préfixe de clé, ses groupes, sa couleur) se déclare
sur l'enum elle-même, jamais ici.

## Services

Le bundle enregistre chaque service dès son installation ou, pour les intégrations, dès que le paquet dont le service a
besoin est installé. Chacun n'agit que sur ce qui le demande.

| Service | Étiquettes | Enregistré | Agit sur |
|---|---|---|---|
| `Infrastructure\Form\EnumTypeExtension` | `form.type_extension` | Toujours | Chaque champ `EnumType` ; ne change rien sans les options de [Formulaires](forms.md), sauf les libellés. |
| `Infrastructure\Form\EnumSetType` | `form.type` | Toujours | Les champs de données `EnumSet` ([Formulaires](forms.md)). |
| `Infrastructure\Validator\EnumCaseValidator`, `Infrastructure\Validator\EnumSetOfValidator` | `validator.constraint_validator` | Toujours | Les contraintes `EnumCase` et `EnumSetOf` ([Validation](validation.md)). |
| `Infrastructure\Validator\TransitionValidator` | `validator.constraint_validator` | Toujours ; reçoit le service `doctrine` s'il existe | La contrainte `Transition` : elle lit le cas précédent dans l'unité de travail de Doctrine ou, sans Doctrine, dans l'objet seul ([Validation](validation.md)). |
| `Infrastructure\Twig\EnumsExtension` | `twig.attribute_extension`, `twig.runtime` | Toujours | Les fonctions et les filtres Twig ([Twig](twig.md)). |
| `Infrastructure\Translation\EnumTranslationExtractor` | `translation.extractor` (alias `phil_enums`) | Avec `symfony/translation` | `translation:extract` ([Traduction](translation.md)). |
| `Infrastructure\Console\DebugEnumsCommand`, `Infrastructure\Console\ExportTypeScriptCommand`, `Infrastructure\Console\ExportIcuCommand` | `console.command` | Avec `symfony/console` | `debug:enums`, `phil:enums:typescript` et `phil:enums:icu` ([Commandes](commands.md)). |
| `Infrastructure\Console\CheckSetColumnsCommand` | `console.command` | Avec `symfony/console` et `doctrine/orm` | `phil:enums:check-sets` ([Doctrine](doctrine.md)). |
| `Infrastructure\Http\ParsedEnumValueResolver` | `controller.argument_value_resolver` | Avec `symfony/http-kernel` | Les arguments marqués `#[ParseEnum]` ([Routage, expressions et workflows](routing-and-workflows.md)). |
| `Infrastructure\ExpressionLanguage\EnumsExpressionProvider` | `security.`, `routing.` et `validator.expression_language_provider` | Avec `symfony/expression-language` | `enum_meta()`, `enum_in_group()`, `enum_from()`. |
| `Infrastructure\OpenApi\NelmioEnumModelDescriber` | `nelmio_api_doc.model_describer`, avant `nelmio_api_doc.model_describers.enum` | Avec `nelmio/api-doc-bundle` | Les modèles d'enum ([API](api.md)). |
| `Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory` | décore `api_platform.metadata.property.metadata_factory`, autour de son décorateur de schéma et à l'intérieur de son cache | Avec `api-platform/symfony` | Les propriétés d'enum des ressources ([API](api.md)). |
| `Infrastructure\Maker\MakeEnum` | `maker.command` | Avec `symfony/maker-bundle` | `make:enum` ([Commandes](commands.md)). |
| `Infrastructure\Serializer\EnumsNormalizer` | `serializer.normalizer`, avant les normaliseurs de Symfony pour les valeurs traduisibles, les cas d'enum et les objets | Avec `symfony/serializer` | Les ensembles d'enums, les tables d'enums et les cas avec libellé ([API](api.md)). |

Chaque service qui affiche un libellé reçoit le service `translator`, ou rien s'il n'y en a pas. Les types de colonne
`EnumSet` ne sont pas des services : le bundle les inscrit dans le registre de types de DBAL à son démarrage, lorsque
`doctrine/dbal` est installé ([Doctrine](doctrine.md)). Les workflows ne sont pas non plus des services du bundle : il
les ajoute en tête des `workflows` de FrameworkBundle, lorsque `symfony/workflow` est installé.
