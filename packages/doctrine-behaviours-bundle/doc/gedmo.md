# Coming from the Gedmo extensions

| Gedmo, StofDoctrineExtensionsBundle | phil |
|---|---|
| `stof_doctrine_extensions.orm.default.timestampable` | `phil_doctrine_behaviours.timestamps` |
| `TimestampableEntity`, `#[Gedmo\Timestampable]` | `Timestampable` and `TimestampableEntity` |
| `#[Gedmo\Blameable]` on a relation to a user | `Blameable` and `BlameableEntity`: the user's identifier |
| `SoftDeleteableEntity`, `#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]` | `SoftDeletable` and `SoftDeletableEntity` |
| `SoftDeleteableFilter`, `softdeleteable` | `SoftDeletedFilter`, `phil_soft_deleted`, added by the bundle |
| `hardDelete: true` | always: removing a deleted entity deletes it |
| `#[Gedmo\Tree(type: 'nested')]`, `TreeLeft`, `TreeRight`, `TreeLevel`, `TreeRoot`, `TreeParent` | `TreeNode` and `TreeNodeEntity`: a path and a depth; the entity maps its parent |
| `NestedTreeRepository::childrenHierarchy()` | `Tree::hierarchy()`, a list of `Branch` |
| `getChildren()`, `getPath()`, `getRootNodes()` | `Tree::children()`, `ancestors()`, `roots()` |
| `stof_doctrine_extensions.orm.default.sluggable`, `#[Gedmo\Slug(fields: ['title'])]` | `phil_doctrine_behaviours.slug`, `Sluggable` and `SluggableEntity`; the entity gives the text in `slugSource()` |
| `#[Gedmo\Slug(updatable: false)]` | always: a slug is made once, and `assignSlug(null)` asks for a new one |
| `stof_doctrine_extensions.orm.default.loggable`, `#[Gedmo\Loggable]`, `#[Gedmo\Versioned]` | `phil_doctrine_behaviours.versions`, `Versionable` and `VersionableEntity`; the entity names its fields in `versionedState()` |
| `LogEntryRepository::getLogEntries()`, `revert()` | `Versions::list()`, `restore()`, under the right `versions.restore_right` |
| `#[Gedmo\Blameable]` on a string column of the owner | `Ownable` and `OwnableEntity`: an `OwnerId`, never an e-mail address |
| `#[Gedmo\Slug(uniqueBase: 'locale')]` | `ScopedSluggableEntity`: the entity names the scope in `slugScope()` and declares the unique index |
| `stof_doctrine_extensions.orm.default.sortable`, `#[Gedmo\SortablePosition]` | `phil_doctrine_behaviours.position`, `Positionable` and `PositionableEntity` |
| `#[Gedmo\SortableGroup]` | `positionGroup()`: the fields whose values make a group |
| `SortableRepository`, changing the position by hand | `Positions::moveFirst()`, `moveLast()`, `moveBefore()`, `moveAfter()` |
| `stof_doctrine_extensions.orm.default.translatable`, `#[Gedmo\Translatable]`, a personal translation entity | `phil_doctrine_behaviours.translation`, `Translatable`, `Translation` and `TranslationEntity`: one row per record and locale |
| `TranslationWalker`, `TranslationRepository::findTranslations()` | `Translations::find()` along a fallback chain, `preload()` for a list, `locales()` |
| `#[Gedmo\Locale]`, `setTranslatableLocale()` | the locale given to `Translations`; a record holds no current locale |

A nested set becomes a materialized path: moving a branch is one update instead of renumbering the tree, and the
order of siblings is the order of their identifiers, or of the comparison `hierarchy()` is given. A blamed relation
to a user becomes the user's identifier: a migration copies it from the user's table.

A log entry becomes a frozen version: Gedmo writes one per flush of a versioned field, while a version here is frozen
when the application says so — a publication, a save an editor names —, and edits until the next freeze stay in the
version being worked on. Going back writes the past state as the next number, so nothing is lost, and takes a right of
its own instead of the right to edit.

A translation is a row of its own, never a copy: a locale with no row falls back along the chain as a whole row,
rather than field by field as Gedmo's fallback does, and writing a locale starts from empty fields. Gedmo's generic
translation table, one row per field and locale, has no counterpart: each translation entity has the translated
columns of its record. A migration moves the rows of `ext_translations` into the translation tables.

Deactivation, publication by workflow and dated identifiers have no Gedmo counterpart. Uploadable, IpTraceable and
ReferenceIntegrity have none here: uploaded files belong to a media library, and an IP address is personal information
that no behaviour copies into a row.
