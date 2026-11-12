<!-- phil-dev source-sha256=d6ca9aad796611fb85dc1159656b600d291cf53832a35a41c113453ad19a53e0 -->
# Points d'extension

La librairie ne déclare aucun service : une application enregistre les écouteurs qu'elle veut sur son gestionnaire
d'entités — voir [Intégration](integration.md). Ce qu'elle implémente, ce sont les interfaces que prennent ses entités,
et qui agit.

## `Contract\BlameResolver`

`Phil\DoctrineBehaviours\Contract\BlameResolver` — qui agit en ce moment, donné à `BlameListener`.

| Méthode | Contrat |
|---|---|
| `current(): ?string` | Un nom stable de l'utilisateur de la requête, de la tâche ou de la commande, ou `null` quand personne n'est connu — rien n'est alors écrit |

`FixedBlame` nomme toujours le même, ou personne : pour les importations, les commandes et les tests. Un résolveur
propre à l'application nomme les utilisateurs par un identifiant qui n'est pas un renseignement personnel là où il le
peut, puisque le nom est copié dans chaque ligne qui garde l'auteur — voir [Horodatage et
auteur](timestamps-and-blame.md#vie-privée).

## `Contract\OwnerResolver`

`Phil\DoctrineBehaviours\Contract\OwnerResolver`, à qui appartiennent les nouveaux enregistrements, donné à
`OwnerListener`.

| Méthode | Contrat |
|---|---|
| `current(): ?OwnerId` | L'identifiant stable du propriétaire, ou `null` quand personne n'est connu ; rien n'est alors écrit |

`FixedOwner` nomme toujours le même, ou personne. Voir [Propriétaire](owner.md).

## `Contract\VersionRestoreGuard`

`Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, le droit de revenir à une version passée, donné à `Versions`.

| Méthode | Contrat |
|---|---|
| `check(Versionable $entity, int $number): void` | Lève quand le droit manque ; `VersionRestoreDenied`, ou une exception propre à l'application |

`AllowVersionRestore` laisse tout le monde, `RefuseVersionRestore` personne. Voir
[Versions](versions.md#revenir-en-arrière).

## Les générateurs de slugs

`SlugListener` prend les `Sluggers` de `phil/slug` : un générateur nommé ou décoré là s'applique ici, et
`Sluggable::sluggerName()` en choisit un pour une entité. Voir [Slug](slug.md).

## Les interfaces des entités

Une entité prend un comportement en implémentant son interface, à la main ou avec le trait qui mappe ses colonnes.

| Interface | Trait | Ce qu'appelle l'écouteur |
|---|---|---|
| `Contract\Timestampable` | `TimestampableEntity` | `stampCreated()`, `stampUpdated()` |
| `Contract\Blameable` | `BlameableEntity` | `blameCreated()`, `blameUpdated()` |
| `Contract\SoftDeletable` | `SoftDeletableEntity` | `markDeleted()` ; `restore()` est à l'application d'appeler — voir [Suppression douce](soft-delete.md) |
| `Contract\TreeNode` | `TreeNodeEntity` | `placeInTree()` — voir [Arbres](trees.md) |
| `Contract\Deactivatable` | `DeactivatableEntity` | `stampDeactivated()` ; `deactivate()` et `reactivate()` sont à l'application d'appeler — voir [Désactivation](deactivation.md) |
| `Contract\Ownable` | `OwnableEntity` | `claimOwner()` ; `transferOwner()` est à l'application d'appeler — voir [Propriétaire](owner.md) |
| `Contract\Publishable` | `PublishableEntity` | `markPublished()`, `markUnpublished()` ; le workflow déplace l'état — voir [Publication](publication.md) |
| `Contract\Positionable` | `PositionableEntity` | `placeAt()` ; `positionGroup()` nomme le groupe — voir [Position](position.md) |
| `Contract\Sluggable` | `SluggableEntity`, `ScopedSluggableEntity` | `assignSlug()` ; `slugScope()` nomme la portée — voir [Slug](slug.md) |
| `Contract\Translatable` | — | `newTranslation()` ; `translationClass()` et `translatedFields()` nomment le reste — voir [Traduction](translation.md) |
| `Contract\Translation` | `TranslationEntity` | `translatable()`, `locale()` — voir [Traduction](translation.md) |
| `Contract\Versionable` | `VersionableEntity` | `forkVersion()` ; `Versions` fige — voir [Versions](versions.md) |
| `Contract\DatedIdentifiable` | `DatedIdentifierEntity` | `assignDatedId()` — voir [Identifiant daté](dated-identifier.md) |

Une entité qui implémente une interface à la main garde ses propres colonnes et ses propres noms ; les écouteurs lisent
et écrivent par les méthodes seulement.

## Les écouteurs et le filtre

| Point | Comment |
|---|---|
| L'heure | L'horloge PSR-20 donnée à `TimestampListener` et à `SoftDeleteListener` : les horodatages sont en UTC |
| Quels comportements s'exécutent | Seulement les écouteurs enregistrés : chacun est indépendant |
| Voir les lignes supprimées | `SoftDeletedFilter`, sous `SoftDeletedFilter::NAME`, désactivé pour les requêtes qui en ont besoin |
| Voir les lignes désactivées | `DeactivatedFilter`, sous `DeactivatedFilter::NAME`, désactivé pour les requêtes qui en ont besoin |
| Effacer le nom d'un utilisateur | `BlameEraser::find()` et `replace()`, sur chaque table qui garde l'auteur |
| Effacer un propriétaire | `OwnerEraser::find()` et `replace()`, sur chaque table qui garde un propriétaire |
| Les locales servies | La `FallbackChain` que l'application donne à `Translations`, tirée du `DeclaredLocales` de `phil/locale-dimensions` |
| Le workflow de publication | Les places et les transitions de `PublicationWorkflow`, ou un workflow propre à l'application avec ces trois places |
