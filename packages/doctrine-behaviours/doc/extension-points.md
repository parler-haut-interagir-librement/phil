# Extension points

The library declares no service: an application registers the listeners it wants on its entity manager — see
[Integration](integration.md). What it implements is the interfaces its entities take on, and who is acting.

## `Contract\BlameResolver`

`Phil\DoctrineBehaviours\Contract\BlameResolver` — who is acting now, given to `BlameListener`.

| Method | Contract |
|---|---|
| `current(): ?string` | A stable name of the user of the request, job or command, or `null` when nobody is known — then nothing is written |

`FixedBlame` names always the same one, or nobody: for imports, commands and tests. A resolver of the application's
own names users by an identifier that is not personal information where it can, since the name is copied into every
blamed row — see [Timestamps and blame](timestamps-and-blame.md#privacy).

## `Contract\OwnerResolver`

`Phil\DoctrineBehaviours\Contract\OwnerResolver`, who new records belong to, given to `OwnerListener`.

| Method | Contract |
|---|---|
| `current(): ?OwnerId` | The stable identifier of the owner, or `null` when nobody is known; then nothing is written |

`FixedOwner` names always the same one, or nobody. See [Owner](owner.md).

## `Contract\VersionRestoreGuard`

`Phil\DoctrineBehaviours\Contract\VersionRestoreGuard`, the right to go back to a past version, given to `Versions`.

| Method | Contract |
|---|---|
| `check(Versionable $entity, int $number): void` | Throws when the right is missing; `VersionRestoreDenied`, or an exception of the application's own |

`AllowVersionRestore` lets everyone, `RefuseVersionRestore` no one. See [Versions](versions.md#going-back).

## `Contract\PersonalFields`

`Phil\DoctrineBehaviours\Contract\PersonalFields`, which fields of a class hold personal information, given to
`Versions` and `VersionEraser`.

| Method | Contract |
|---|---|
| `of(string $class): list<string>` | The names of the fields, as `versionedState()` keys them; `[]` when the class holds none |

`NoPersonalFields` names none, `ListedPersonalFields` those listed by class or interface. The bundle reads the
`#[PersonalData]` declarations of `phil/privacy-bundle`. See [Versions](versions.md#personal-fields).

## `Contract\VersionCipher`

`Phil\DoctrineBehaviours\Contract\VersionCipher`, which seals the personal fields of a version under
`PersonalFieldPolicy::Encrypt`, given to `Versions`.

| Method | Contract |
|---|---|
| `seal(string $plaintext, string $context): string` | The sealed value, bound to the context as associated data |
| `open(string $sealed, string $context): string` | The value, or an exception when it does not open under that context |

The plaintext is the JSON of the value; the context names the table, the record, the version and the field. An
authenticated cipher, such as XChaCha20-Poly1305 with the context as associated data, under a key the application keeps
out of the database, fits; erasing a version still takes `VersionEraser`, since a key that is kept opens what it sealed.

## The sluggers

`SlugListener` takes the `Sluggers` of `phil/slug`: a slugger named or decorated there applies here, and
`Sluggable::sluggerName()` picks one for an entity. See [Slug](slug.md).

## The entity interfaces

An entity takes on a behaviour by implementing its interface, by hand or with the trait that maps its columns.

| Interface | Trait | What the listener calls |
|---|---|---|
| `Contract\Timestampable` | `TimestampableEntity` | `stampCreated()`, `stampUpdated()` |
| `Contract\Blameable` | `BlameableEntity` | `blameCreated()`, `blameUpdated()` |
| `Contract\SoftDeletable` | `SoftDeletableEntity` | `markDeleted()`; `restore()` is the application's to call — see [Soft delete](soft-delete.md) |
| `Contract\TreeNode` | `TreeNodeEntity` | `placeInTree()` — see [Trees](trees.md) |
| `Contract\Deactivatable` | `DeactivatableEntity` | `stampDeactivated()`; `deactivate()` and `reactivate()` are the application's to call — see [Deactivation](deactivation.md) |
| `Contract\Ownable` | `OwnableEntity` | `claimOwner()`; `transferOwner()` is the application's to call — see [Owner](owner.md) |
| `Contract\Publishable` | `PublishableEntity` | `markPublished()`, `markUnpublished()`; the workflow moves the state — see [Publication](publication.md) |
| `Contract\Positionable` | `PositionableEntity` | `placeAt()`; `positionGroup()` names the group — see [Position](position.md) |
| `Contract\Sluggable` | `SluggableEntity`, `ScopedSluggableEntity` | `assignSlug()`; `slugScope()` names the scope — see [Slug](slug.md) |
| `Contract\Translatable` | — | `newTranslation()`; `translationClass()` and `translatedFields()` name the rest — see [Translation](translation.md) |
| `Contract\Translation` | `TranslationEntity` | `translatable()`, `locale()` — see [Translation](translation.md) |
| `Contract\Versionable` | `VersionableEntity` | `forkVersion()`; `Versions` freezes — see [Versions](versions.md) |
| `Contract\DatedIdentifiable` | `DatedIdentifierEntity` | `assignDatedId()` — see [Dated identifier](dated-identifier.md) |

An entity that implements an interface by hand keeps the columns and names of its own; the listeners read and write
through the methods only.

## Listeners and the filter

| Point | How |
|---|---|
| The time | The PSR-20 clock given to `TimestampListener`, `SoftDeleteListener`, `DeactivationListener`, `PublicationListener`, `DatedIdentifierListener` and `Versions`, in any time zone: the columns keep UTC — see [Moments in UTC](#moments-in-utc) |
| Which behaviours run | Only the listeners registered: each stands alone |
| Seeing deleted rows | `SoftDeletedFilter`, under `SoftDeletedFilter::NAME`, disabled for the queries that need them |
| Seeing deactivated rows | `DeactivatedFilter`, under `DeactivatedFilter::NAME`, disabled for the queries that need them |
| Erasing a user's name | `BlameEraser::find()` and `replace()`, over every blamed table of every entity manager a `ManagerRegistry` gives |
| Erasing an owner | `OwnerEraser::find()` and `replace()`, over every owned table of every entity manager a `ManagerRegistry` gives |
| Erasing the personal fields of the versions | `VersionEraser::redact()`, `redactRecord()` and `stripPersonalFields()` |
| No blame during an erasure | A `BlameSuspension` given to `BlameListener`, its `during()` around the erasure |
| Deleting a soft-deletable row for good | `SoftDeletes::purge()` |
| The collation of the keys | `BinaryKeysListener`, on `loadClassMetadata`, and its `FIELDS` |
| The locales served | The `FallbackChain` the application gives `Translations`, from the `DeclaredLocales` of `phil/locale-dimensions` |
| The publication workflow | `PublicationWorkflow`'s places and transitions, or a workflow of the application's own with those three places |

## Moments in UTC

Every column that marks a moment — `created_at`, `updated_at`, `deleted_at`, `deactivated_at`, `published_at`,
`unpublished_at` and the `frozen_at` of `phil_record_version` — has the DBAL type `datetime_utc_immutable`. DBAL writes
the moment in UTC whatever the time zone of the `DateTimeImmutable` it is given, and reads it back in UTC, whatever the
time zone of PHP or of the database server: two servers in different zones read the same moment, and a query that
compares a column with a moment compares like with like.

A stamp keeps the time zone of the clock until the entity is read again: `createdAt()` right after the flush is the
clock's moment, `2026-11-04T08:00:00-05:00`, and the same moment read back is `2026-11-04T13:00:00+00:00`. Compare
moments with `<`, `>` or their timestamps, never with `==`, which a coding standard may turn into `===`, an identity
test between objects. To show a moment in a visitor's time zone, convert it with `setTimezone()` at display.

A table created before these columns had this type keeps its schema: see [Upgrading](upgrading.md).
