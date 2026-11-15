<!-- phil-dev source-sha256=203a0f82fed45f8778b97a9a09bda1a00d63dd7b268f140c5e5a7ccccf382ee6 -->
# Points d'extension

Ce qu'une application configure, remplace ou utilise. Les comportements et leurs interfaces sont ceux de
`phil/doctrine-behaviours`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `timestamps`, `blame`, `soft_delete`, `tree` | Les écouteurs enregistrés : chaque comportement est indépendant |
| `blame.user_method` | La méthode de la classe d'utilisateur qui le nomme dans l'auteur, un accesseur d'UUID plutôt que `getUserIdentifier()` |
| `owner.user_method` | La méthode de la classe d'utilisateur qui donne l'identifiant stable d'un propriétaire |
| `publication.workflow`, `publication.define_workflow` | Le nom du workflow de publication, et si l'application déclare le sien |
| `versions.restore_right` | L'attribut de Symfony Security nécessaire pour revenir à une version passée |
| `deactivation.filter` | Si `phil_deactivated` est ajouté au gestionnaire d'entités par défaut |
| `position` | Si `PositionListener` et `Positions` sont enregistrés |
| `translation` | Si `TranslationListener` et `Translations` sont enregistrés |
| `privacy`, `privacy.blame_replacement`, `privacy.owner_replacement` | Si l'auteur et le propriétaire répondent à `phil/privacy-bundle`, et les pseudonymes que laisse un effacement : voir [Configuration](configuration.md) |
| `soft_delete.filter` | Si `phil_soft_deleted` est ajouté au gestionnaire d'entités par défaut ; `false` pour l'ajouter à la main à plusieurs : voir [Configuration](configuration.md) |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\DoctrineBehaviours\Contract\BlameResolver` | Remplacé par un service propre à l'application (`Phil\DoctrineBehaviours\Contract\BlameResolver: '@App\Blame\CommandBlame'`) pour nommer quelqu'un d'autre que l'utilisateur du jeton ; `SecurityBlameResolver` avec Symfony Security, personne sans lui |
| `Phil\DoctrineBehaviours\BlameEraser` | Injecté par les demandes d'accès et d'effacement de l'application ; public |
| `Phil\DoctrineBehaviours\Tree` | Injecté pour lire et déplacer des branches ; public |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver` | Remplacé par un service propre à l'application pour nommer un autre propriétaire ; `SecurityOwnerResolver` avec Symfony Security, personne sans lui |
| `Phil\DoctrineBehaviours\OwnerEraser` | Injecté par les demandes d'accès et d'effacement de l'application ; public |
| `Phil\DoctrineBehaviours\Versions` | Injecté pour figer, lister et revenir en arrière ; public |
| `Phil\DoctrineBehaviours\Positions` | Injecté pour déplacer un enregistrement avant ou après un autre ; public |
| `Phil\DoctrineBehaviours\Translations` | Injecté pour lire le texte d'un enregistrement le long d'une chaîne de repli, précharger une liste et écrire une locale ; public. La chaîne vient d'un `DeclaredLocales` de `phil/locale-dimensions` que l'application enregistre, ou de celui de `phil/content-bundle` |
| `…\Infrastructure\Privacy\BlamePrivacy`, `…\Infrastructure\Privacy\OwnerPrivacy` | Pris par `phil/privacy-bundle` par ses étiquettes `phil_privacy.exporter` et `phil_privacy.eraser` ; `privacy: false` les laisse de côté pour une application qui répond elle-même pour l'auteur et le propriétaire |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard` | Remplacé pour décider qui revient à une version passée ; `SecurityVersionRestoreGuard` avec Symfony Security, `RefuseVersionRestore` sans lui |

## Les entités

Une entité adopte un comportement en implémentant son interface (`Timestampable`, `Blameable`, `SoftDeletable`,
`TreeNode`, `Deactivatable`, `Ownable`, `Publishable`, `Positionable`, `Sluggable`, `Translatable` et `Translation`,
`Versionable`, `DatedIdentifiable`), à la main ou avec son trait : les écouteurs lisent et écrivent par les méthodes seulement.

| Méthode | Ce que décide l'entité |
|---|---|
| `Positionable::positionGroup()` | Les champs dont les valeurs forment un groupe ordonné à part ; une liste vide pour un seul ordre dans la table |
| `Sluggable::slugScope()` | Les champs dans lesquels un *slug* est unique, avec `ScopedSluggableEntity` et l'index unique que déclare l'entité |
| `Sluggable::sluggerName()` | Le slugger de `phil/slug-bundle` qui fabrique le *slug* ; celui par défaut quand elle vaut `null` |
| `Translatable::translationClass()`, `translatedFields()`, `newTranslation()` | L'entité de traduction, ses champs traduits, et la façon de fabriquer la ligne d'une locale |

## L'horloge

Les instants viennent du service `clock` de Symfony, converti en UTC. Une application ou un test qui remplace l'horloge
(`MockClock`, `ClockSensitiveTrait`) fixe chaque instant qu'écrivent les comportements.
