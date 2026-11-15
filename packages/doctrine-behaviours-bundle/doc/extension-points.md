# Extension points

What an application configures, replaces or relies on. The behaviours and their interfaces are
`phil/doctrine-behaviours`'s.

## Configuration

| Option | What it extends |
|---|---|
| `timestamps`, `blame`, `soft_delete`, `tree` | Which listeners are registered: each behaviour stands alone |
| `blame.user_method` | The method of the user class that names them in the blame, a UUID getter rather than `getUserIdentifier()` |
| `owner.user_method` | The method of the user class that gives the stable identifier of an owner |
| `publication.workflow`, `publication.define_workflow` | The name of the publication workflow, and whether the application declares its own |
| `versions.restore_right` | The Symfony Security attribute needed to go back to a past version |
| `deactivation.filter` | Whether `phil_deactivated` is added to the default entity manager |
| `position` | Whether `PositionListener` and `Positions` are registered |
| `translation` | Whether `TranslationListener` and `Translations` are registered |
| `privacy`, `privacy.blame_replacement`, `privacy.owner_replacement` | Whether the blame and the owner answer `phil/privacy-bundle`, and the pseudonyms an erasure leaves — see [Configuration](configuration.md) |
| `soft_delete.filter` | Whether `phil_soft_deleted` is added to the default entity manager; `false` to add it by hand to several — see [Configuration](configuration.md) |

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\DoctrineBehaviours\Contract\BlameResolver` | Replaced by a service of the application's own — `Phil\DoctrineBehaviours\Contract\BlameResolver: '@App\Blame\CommandBlame'` — to name someone other than the user of the token; `SecurityBlameResolver` with Symfony Security, nobody without it |
| `Phil\DoctrineBehaviours\BlameEraser` | Injected by the application's access and erasure requests; public |
| `Phil\DoctrineBehaviours\Tree` | Injected to read and move branches; public |
| `Phil\DoctrineBehaviours\Contract\OwnerResolver` | Replaced by a service of the application's own to name another owner; `SecurityOwnerResolver` with Symfony Security, nobody without it |
| `Phil\DoctrineBehaviours\OwnerEraser` | Injected by the application's access and erasure requests; public |
| `Phil\DoctrineBehaviours\Versions` | Injected to freeze, list and go back; public |
| `Phil\DoctrineBehaviours\Positions` | Injected to move a record before or after another; public |
| `Phil\DoctrineBehaviours\Translations` | Injected to read a record's text along a fallback chain, preload a list, and write a locale; public. The chain comes from a `DeclaredLocales` of `phil/locale-dimensions` the application registers, or the one of `phil/content-bundle` |
| `…\Infrastructure\Privacy\BlamePrivacy`, `…\Infrastructure\Privacy\OwnerPrivacy` | Taken by `phil/privacy-bundle` through its tags `phil_privacy.exporter` and `phil_privacy.eraser`; `privacy: false` leaves them out for an application that answers for the blame and the owner itself |
| `Phil\DoctrineBehaviours\Contract\VersionRestoreGuard` | Replaced to decide who goes back to a past version; `SecurityVersionRestoreGuard` with Symfony Security, `RefuseVersionRestore` without it |

## The entities

An entity takes on a behaviour by implementing its interface — `Timestampable`, `Blameable`, `SoftDeletable`,
`TreeNode`, `Deactivatable`, `Ownable`, `Publishable`, `Positionable`, `Sluggable`, `Translatable` and `Translation`,
`Versionable`, `DatedIdentifiable` —
by hand or with its trait: the listeners read and write through the methods only.

| Method | What the entity decides |
|---|---|
| `Positionable::positionGroup()` | The fields whose values make a group ordered on its own; an empty list for one order in the table |
| `Sluggable::slugScope()` | The fields within which a slug is unique, with `ScopedSluggableEntity` and the unique index the entity declares |
| `Sluggable::sluggerName()` | The slugger of `phil/slug-bundle` that makes the slug; its default one when `null` |
| `Translatable::translationClass()`, `translatedFields()`, `newTranslation()` | The translation entity, its translated fields, and how a row of a locale is made |

## The clock

The instants come from the `clock` service of Symfony, converted to UTC. An application or a test that replaces the
clock — `MockClock`, `ClockSensitiveTrait` — sets every instant the behaviours write.
