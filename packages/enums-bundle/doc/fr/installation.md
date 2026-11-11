<!-- phil-dev source-sha256=87ff0ca2160d35941c7de7c3ed43cfa38141ae47821bfd472ba671ff5221727b -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent, `ext-mbstring` | Les clés de traduction générées mettent en minuscules des noms de cas qui peuvent contenir des lettres hors ASCII. |
| `phil/enums` | Les métadonnées (`Label`, `Description`, `Group`), le lecteur de métadonnées et `CaseCollection`. |
| `symfony/form`, `symfony/options-resolver` | L'extension de `EnumType`. |
| `symfony/validator` | La contrainte `EnumCase`. |
| `symfony/translation-contracts` | `TranslatableInterface`, qu'implémentent les enums traduisibles. |
| `twig/twig` | Les fonctions et les filtres Twig. |
| `symfony/dependency-injection`, `symfony/config` | La classe du bundle et sa configuration. |
| `ext-tokenizer` | Le chercheur des commandes et de l'extracteur de traductions lit les fichiers PHP par leurs jetons. |

Plusieurs paquets sont facultatifs : le bundle n'enregistre ce qui en a besoin que s'ils sont installés, et il les
suggère.

| Paquet facultatif | Active |
|---|---|
| `symfony/translation` | L'extracteur de traductions et la vérification des traductions de `debug:enums` ([Traduction](translation.md)). |
| `symfony/console` | Les commandes `debug:enums` et `phil:enums:typescript` ([Commandes](commands.md)). |
| `symfony/serializer` | Le normaliseur des ensembles d'enums, des tables d'enums et des cas avec libellé ([API](api.md)). |
| `doctrine/dbal` | Les types de colonne `EnumSet` ([Doctrine](doctrine.md)). |
| `doctrine/orm` | La commande `phil:enums:check-sets`. |
| `symfony/http-kernel` | Le résolveur d'arguments de contrôleur `#[ParseEnum]`. |
| `symfony/expression-language` | Les fonctions d'expression. |
| `api-platform/symfony` | Les schémas d'enum des ressources API Platform ([API](api.md)). |
| `nelmio/api-doc-bundle` | Les schémas d'enum des modèles NelmioApiDoc ([API](api.md)). |
| `symfony/maker-bundle` | `make:enum` ([Commandes](commands.md)). |
| `symfony/workflow` | Les machines à états construites à partir de `#[TransitionsTo]` ([Routage, expressions et workflows](routing-and-workflows.md)). |

L'application enregistre FrameworkBundle, et TwigBundle pour la partie Twig : le bundle étiquette ses services pour eux
(`form.type_extension`, `validator.constraint_validator`, `twig.attribute_extension`, `twig.runtime`) et utilise le
service `translator` s'il existe.

## Installer

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

Sa configuration est facultative : voir [Configuration](configuration.md).
