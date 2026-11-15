<!-- phil-dev source-sha256=53d70121ae46732d3e96297c89056842d1bcbc0933cdb9915fc4dab89e940b6c -->
# Venir des extensions Gedmo

| Gedmo, StofDoctrineExtensionsBundle | phil |
|---|---|
| `stof_doctrine_extensions.orm.default.timestampable` | `phil_doctrine_behaviours.timestamps` |
| `TimestampableEntity`, `#[Gedmo\Timestampable]` | `Timestampable` et `TimestampableEntity` |
| `#[Gedmo\Blameable]` sur une relation vers un utilisateur | `Blameable` et `BlameableEntity` : l'identifiant de l'utilisateur |
| `SoftDeleteableEntity`, `#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]` | `SoftDeletable` et `SoftDeletableEntity` |
| `SoftDeleteableFilter`, `softdeleteable` | `SoftDeletedFilter`, `phil_soft_deleted`, ajouté par le bundle |
| `hardDelete: true` | toujours : retirer une entité supprimée la supprime |
| `#[Gedmo\Tree(type: 'nested')]`, `TreeLeft`, `TreeRight`, `TreeLevel`, `TreeRoot`, `TreeParent` | `TreeNode` et `TreeNodeEntity` : un chemin et une profondeur ; l'entité associe son parent |
| `NestedTreeRepository::childrenHierarchy()` | `Tree::hierarchy()`, une liste de `Branch` |
| `getChildren()`, `getPath()`, `getRootNodes()` | `Tree::children()`, `ancestors()`, `roots()` |
| `stof_doctrine_extensions.orm.default.sluggable`, `#[Gedmo\Slug(fields: ['title'])]` | `phil_doctrine_behaviours.slug`, `Sluggable` et `SluggableEntity` ; l'entité donne le texte dans `slugSource()` |
| `#[Gedmo\Slug(updatable: false)]` | toujours : un *slug* est fait une fois, et `assignSlug(null)` en demande un nouveau |
| `stof_doctrine_extensions.orm.default.loggable`, `#[Gedmo\Loggable]`, `#[Gedmo\Versioned]` | `phil_doctrine_behaviours.versions`, `Versionable` et `VersionableEntity` ; l'entité nomme ses champs dans `versionedState()` |
| `LogEntryRepository::getLogEntries()`, `revert()` | `Versions::list()`, `restore()`, sous le droit `versions.restore_right` |
| `#[Gedmo\Blameable]` sur une colonne texte du propriétaire | `Ownable` et `OwnableEntity` : un `OwnerId`, jamais une adresse courriel |
| `#[Gedmo\Slug(uniqueBase: 'locale')]` | `ScopedSluggableEntity` : l'entité nomme la portée dans `slugScope()` et déclare l'index unique |
| `stof_doctrine_extensions.orm.default.sortable`, `#[Gedmo\SortablePosition]` | `phil_doctrine_behaviours.position`, `Positionable` et `PositionableEntity` |
| `#[Gedmo\SortableGroup]` | `positionGroup()` : les champs dont les valeurs forment un groupe |
| `SortableRepository`, changer la position à la main | `Positions::moveFirst()`, `moveLast()`, `moveBefore()`, `moveAfter()` |
| `stof_doctrine_extensions.orm.default.translatable`, `#[Gedmo\Translatable]`, une entité de traduction personnelle | `phil_doctrine_behaviours.translation`, `Translatable`, `Translation` et `TranslationEntity` : une ligne par enregistrement et par locale |
| `TranslationWalker`, `TranslationRepository::findTranslations()` | `Translations::find()` le long d'une chaîne de repli, `preload()` pour une liste, `locales()` |
| `#[Gedmo\Locale]`, `setTranslatableLocale()` | la locale donnée à `Translations` ; un enregistrement ne garde aucune locale courante |

Un ensemble imbriqué devient un chemin matérialisé : déplacer une branche est une seule mise à jour au lieu d'une
renumérotation de l'arbre, et l'ordre des frères est celui de leurs identifiants, ou de la comparaison que reçoit
`hierarchy()`. Une relation tracée vers un utilisateur devient l'identifiant de l'utilisateur : une migration le copie
depuis la table des utilisateurs.

Une entrée de journal devient une version figée : Gedmo en écrit une à chaque *flush* d'un champ versionné, alors
qu'ici une version est figée quand l'application le dit (une publication, un enregistrement que nomme un rédacteur), et
les modifications jusqu'au prochain gel restent dans la version en cours. Revenir en arrière écrit l'état passé sous le
numéro suivant, si bien que rien n'est perdu, et exige un droit qui lui est propre au lieu du droit de modifier.

Une traduction est une ligne à part, jamais une copie : une locale sans ligne se replie le long de la chaîne comme une
ligne entière, et non champ par champ comme le fait le repli de Gedmo, et écrire une locale part de champs vides. La
table de traduction générique de Gedmo, une ligne par champ et par locale, n'a pas d'équivalent : chaque entité de
traduction a les colonnes traduites de son enregistrement. Une migration déplace les lignes de `ext_translations` dans
les tables de traduction.

La désactivation, la publication par workflow et les identifiants datés n'ont pas d'équivalent Gedmo. Uploadable,
IpTraceable et ReferenceIntegrity n'en ont aucun ici : les fichiers téléversés appartiennent à une médiathèque, et une
adresse IP est un renseignement personnel qu'aucun comportement ne copie dans une ligne.
