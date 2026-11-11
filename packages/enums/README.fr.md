<!-- phil-dev source-sha256=d45a973309bacb15765758b2d556ddc78e584d709175d9944451cbcd3ea060aa -->
# phil/enums

**Des traits et des attributs qui rendent les enums PHP plus faciles à énumérer, à comparer, à décrire et à regrouper en
ensembles.**

phil/enums ajoute ce que les enums natives laissent de côté. Il lit les cas sous forme de noms, de valeurs ou d'options,
et les compte, les filtre, les regroupe, les trie et les tire au sort. Il compare les cas et les parcourt dans l'ordre,
trouve un cas par son nom ou l'analyse avec souplesse, et indique si l'enum est pure ou adossée. Des attributs associent
des métadonnées aux cas, comme un libellé, une description, un groupe ou un poids, et `EnumSet` et `EnumMap` rangent les
cas dans des ensembles et des tables associatives.

Il s'adresse aux développeurs PHP dont les enums portent plus qu'une liste de cas. Il ne valide pas les entrées,
n'associe pas les enums à une base de données ou à un formulaire et ne traduit pas les libellés : il rend seulement
l'enum elle-même plus utile. Dans une application Symfony, `phil/enums-bundle` ajoute par-dessus la traduction, les
formulaires, la validation, Twig, Doctrine et les contrats d'API.

C'est une simple bibliothèque PHP, sans dépendance à un paquet, utilisable dans n'importe quel code. Chaque trait ajoute
des méthodes publiques à l'API de l'enum : une enum n'utilise donc que les traits dont elle a besoin. Des extensions
PHPStan typent les méthodes qu'ajoutent les traits et vérifient les métadonnées requises.

## État

| Partie | État |
|---|---|
| Traits : invocable, nameable, valuable, selectable, string-selectable, comparable, fromamble, metadatable, self-awareable, collectable, enumerable | Implémenté et testé |
| Métadonnées : `Meta`, `Description`, `Group`, `Label`, `AbstractMetaProperty`, propriétés méta déclarées par des traits | Implémenté et testé |
| Extensions PHPStan : appels statiques de cas, accesseurs de métadonnées | Implémenté |
| Opérations sur les cas : `CaseCollection` — compter, premier et dernier, filtrer, rejeter, only et except, `whereMeta()`, tris, map | Implémenté et testé |
| Métadonnées lues une fois par enum et par cas, conservées pour le processus | Implémenté et testé |
| Regroupement, partition, `keyBy()`, `pluck()`, `random()` pondéré | Implémenté et testé |
| `EnumSet` (opérations d'ensemble, masques de bits) et `EnumMap` totale | Implémenté et testé |
| Ordinaux, `parse()` avec `#[Alias]`, `#[DefaultCase]`, cas obsolètes | Implémenté et testé |
| `#[TransitionsTo]` et `AsStatefulEnumTrait` | Implémenté et testé |
| `SortDirection`, partagé par les tris, les requêtes et le SQL | Implémenté et testé |
| `EnumSet::fromValues()`, `EnumMap::fromPartial()`, `#[BitFlags]` | Implémenté et testé |
| `#[RequiredMeta]` : règle PHPStan et vérification à l'exécution | Implémenté et testé |
| Règles PHPStan des déclarations d'enums et des ordinaux stockés | Implémenté, vérifié par l'analyse des jeux de données d'essai |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le trait agrégé, et quand préférer les traits individuels. |
| [Valeurs, noms et options](doc/fr/values-and-names.md) | `__invoke()` et les appels statiques de cas, `names()`, `values()`, `options()` et `stringOptions()`. |
| [Opérations](doc/fr/operations.md) | Le décompte, le premier et le dernier, le filtrage par fonction de rappel ou par propriété méta, le regroupement, le tri, les tirages au sort, et la `CaseCollection` immuable. |
| [Ensembles et tables associatives](doc/fr/sets-and-maps.md) | `EnumSet`, ses opérations d'ensemble et ses masques de bits ; l'`EnumMap` totale. |
| [Ordre, analyse, valeurs par défaut et transitions](doc/fr/order-and-parsing.md) | Les ordinaux, `parse()` et `#[Alias]`, `#[DefaultCase]`, `#[TransitionsTo]`, les cas obsolètes. |
| [Comparaison](doc/fr/comparison.md) | `is()`, `isNot()`, `in()`, `notIn()`, `has()`, `doesntHave()`, `equalsOneOf()`, `notEqualsOneOf()`. |
| [Recherche par nom](doc/fr/lookup-by-name.md) | `from()` et `tryFrom()` pour les enums pures, `fromName()` et `tryFromName()` pour toutes les enums. |
| [Métadonnées](doc/fr/metadata.md) | L'association de valeurs aux cas, l'écriture de propriétés méta, les accesseurs, `fromMeta()`, les traits qui déclarent des propriétés méta, les propriétés fournies, les métadonnées requises. |
| [Conscience de soi](doc/fr/self-awareness.md) | `isPure()`, `isBacked()`, `isBackedByInteger()`, `isBackedByString()`. |
| [PHPStan](doc/fr/phpstan.md) | L'inclusion des extensions, ce qu'elles typent, et les règles : métadonnées requises, déclarations, ordinaux stockés. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et la façon de tester vos enums. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

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

## Mise à niveau depuis ph-il/enums

Ce paquet a été publié sous le nom `ph-il/enums` jusqu'à 0.2.2 ; il s'appelle `phil/enums` depuis 0.3.0, et
`ph-il/enums` est abandonné à son profit. L'espace de noms reste `Phil\Enums` : remplacez la dépendance par
`composer remove ph-il/enums` puis `composer require phil/enums`, et lisez le [journal des modifications](CHANGELOG.md)
pour les changements de 0.3.0.

## Prérequis

PHP 8.5 ou plus récent et `ext-mbstring` ; PHPStan 2 pour les extensions.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
