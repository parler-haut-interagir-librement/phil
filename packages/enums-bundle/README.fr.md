<!-- phil-dev source-sha256=ca1cf602aea2ab320ac208987ff6b97b268433f6b7aeaae5a60899898fa6d5a9 -->
# phil/enums-bundle

**Vos enums PHP dans les formulaires, les traductions, Twig, la validation, Doctrine et les contrats d'API de Symfony.**

phil/enums-bundle est l'intégration Symfony de `phil/enums`. Il traduit les cas d'une enum selon leurs métadonnées
`Label` et `Description`. Les champs de formulaire n'offrent qu'un sous-ensemble d'une enum (un groupe, une liste, les
cas qui ont une méta-valeur donnée) ou contiennent des données `EnumSet`, et une contrainte de validation accepte
exactement ce sous-ensemble. Twig reçoit des fonctions, des filtres et des badges. Un extracteur de traductions, une
commande `debug:enums` et un export TypeScript servent le développeur. JSON Schema, un normaliseur du sérialiseur et des
types de colonne Doctrine pour les ensembles d'enums servent les contrats d'API et le stockage.

Il s'adresse aux applications Symfony dont les enums se rendent à l'écran, à la base de données ou à une API. Il ne fait
pas correspondre un cas unique à la base de données, car `enumType` de Doctrine le fait déjà. Il ne lit pas non plus les
cas d'une requête : le résolveur d'arguments de Symfony s'en charge, et le `#[ParseEnum]` de ce bundle le fait avec plus
de souplesse. Hors de Symfony, `phil/enums` seul fournit les traits et les attributs.

Ce qui varie d'une enum à l'autre se déclare sur l'enum elle-même, avec les attributs de `phil/enums`. La configuration
indique seulement où les commandes cherchent, quelles colonnes d'ensemble existent et quelles enums sont des workflows.
Les intégrations facultatives s'activent lorsque leur paquet est installé : la console, le sérialiseur, le langage
d'expression, Symfony Workflow, API Platform, NelmioApiDoc et le maker.

## État

| Partie | État |
|---|---|
| `CaseSelection` : sous-ensembles par `only`, `except`, groupes et méta-valeurs | Implémenté et testé |
| Traduction : `AsTranslatableEnumTrait`, `Translatable`, `TranslatableText`, clés tirées de `Label` et `Description` | Implémenté et testé |
| Formulaires : options `group`, `only`, `except`, `where_meta` de `EnumType`, libellés tirés des métadonnées | Implémenté et testé |
| Validation : la contrainte `EnumCase` | Implémenté et testé |
| Twig : `enum_cases()`, `enum_label`, `enum_description`, `enum_meta`, `enum_badge` avec `Color` et `Icon` | Implémenté et testé |
| Formulaires : `without_deprecated`, `group_by_meta`, `preferred_group` ; `EnumSetType` | Implémenté et testé |
| Extracteur de traductions pour `translation:extract` | Implémenté et testé |
| Commandes : `debug:enums`, `phil:enums:typescript` | Implémenté et testé |
| `EnumSchema` (JSON Schema) et `EnumsNormalizer` (sérialiseur) | Implémenté et testé |
| Doctrine : types de colonne `EnumSet`, masque de bits ou JSON ; `phil:enums:check-sets` | Implémenté et testé |
| Validation : `EnumSetOf` ; le sérialiseur relit les ensembles et les tables de correspondance | Implémenté et testé |
| Validation : `Transition`, un changement d'état que l'enum déclare | Implémenté et testé |
| Arguments de contrôleur `#[ParseEnum]`, fonctions d'expression, workflows tirés de `#[TransitionsTo]` | Implémenté et testé |
| Export TypeScript avec descriptions, groupes, valeur par défaut et `--check` ; `phil:enums:icu` | Implémenté et testé |
| Schémas d'enum dans API Platform et NelmioApiDoc ; `make:enum` | Implémenté et testé |
| Un test de noyau pour chaque intégration | Implémenté |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du bundle. |
| [Configuration](doc/fr/configuration.md) | Les trois clés facultatives, et les services que le bundle enregistre avec leurs étiquettes. |
| [Sélection des cas](doc/fr/selection.md) | `CaseSelection` : les quatre critères communs à toutes les parties, et les groupes comme sous-ensembles nommés. |
| [Traduction](doc/fr/translation.md) | Les enums traduisibles, les clés de libellé et de description, le domaine et le préfixe, et l'extraction des clés. |
| [Formulaires](doc/fr/forms.md) | Les options de `EnumType`, le libellé d'un choix, et `EnumSetType`. |
| [Validation](doc/fr/validation.md) | Les contraintes `EnumCase` et `EnumSetOf`, ce qu'elles lisent et leurs codes d'erreur. |
| [Twig](doc/fr/twig.md) | Les fonctions, les filtres et les badges. |
| [Commandes](doc/fr/commands.md) | `debug:enums`, `phil:enums:typescript`, `phil:enums:icu`, `phil:enums:check-sets` et `make:enum`. |
| [API](doc/fr/api.md) | Le normaliseur du sérialiseur, la relecture des ensembles et des tables de correspondance, le JSON Schema d'une enum, API Platform et NelmioApiDoc. |
| [Doctrine](doc/fr/doctrine.md) | Le stockage d'un `EnumSet` dans une colonne, en masque de bits ou en liste JSON, et la vérification des valeurs stockées. |
| [Routage, expressions et workflows](doc/fr/routing-and-workflows.md) | `#[ParseEnum]`, les fonctions d'expression et les machines à états tirées de `#[TransitionsTo]`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application déclare sur ses enums, ses formulaires et ses propriétés. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester vos enums, formulaires et contraintes. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

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

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` et `ext-tokenizer`, `phil/enums`, et les composants de formulaire et de
validation ainsi que les contrats de traduction de Symfony 8.2 ; Twig 3.21 ou plus récent pour la partie Twig.
Facultatifs : `symfony/translation`, `symfony/console`, `symfony/serializer`, `doctrine/dbal`, `doctrine/orm`,
`symfony/http-kernel`, `symfony/expression-language` et `symfony/workflow`, API Platform, NelmioApiDoc et MakerBundle,
pour les parties qui en ont besoin.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
