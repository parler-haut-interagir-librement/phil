# Changelog

All notable changes to `phil/doctrine-behaviours-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-20

A privacy security fix: the versions take part in the access and erasure requests, the
erasers reach every entity manager, no name is blamed during an erasure, and a search index sees a soft deletion. A
minor version and not a patch: the configuration has new options, `phil_record_version` has the column `redacted_at`,
and `Infrastructure\Doctrine\BinaryKeysListener` is `Phil\DoctrineBehaviours\BinaryKeysListener`. After the
migration that adds `redacted_at`, run `bin/console phil:doctrine-behaviours:strip-versions` once to take the
`#[PersonalData]` fields out of the versions already written. See
[The personal fields of the versions](doc/configuration.md#the-personal-fields-of-the-versions).

### Added

- `versions.personal_fields`, `exclude` by default, `encrypt` or `keep`, and `versions.cipher`, the service of a
  `VersionCipher` that `encrypt` requires: what a frozen version keeps of the `#[PersonalData]` fields of its record.
- `Infrastructure\Privacy\PersonalDataFields`: the personal fields of a class are its properties declared
  `#[PersonalData]`, its parents' included; the service `Phil\DoctrineBehaviours\Contract\PersonalFields`.
- `Infrastructure\Privacy\VersionsPrivacy`, the module `behaviours.versions`: the versions that still hold a person's
  personal fields counted for an access request, under `versions_with_personal_fields`, and redacted for an erasure,
  reported as `redacted` in the tables of the versions.
- The public service `Phil\DoctrineBehaviours\VersionEraser`, and `Infrastructure\Console\StripVersionsCommand`, the
  command `phil:doctrine-behaviours:strip-versions` with `--dry-run`, registered with `symfony/console`.
- `blame.suspend_during_erasure`, true by default: `Infrastructure\DependencyInjection\SuspendBlameDuringErasurePass`
  decorates every service tagged `phil_privacy.eraser` with `Infrastructure\Privacy\SuspendBlame`, or
  `SuspendBlameWithExport` for an eraser that also exports, so that no name is blamed while it runs; the service
  `Phil\DoctrineBehaviours\BlameSuspension`, reset with `kernel.reset`.
- The public service `Phil\DoctrineBehaviours\SoftDeletes`, whose `purge()` deletes a soft-deletable row for good.
- `BehaviourServices::SOFT_DELETE_PRIORITY`, `-256`, and `BehaviourServices::ERASER_TAG`.

### Changed

- `BlamePrivacy` and `OwnerPrivacy` redact the versions of the person, with the versions on, before they replace the
  name the versions are found by.
- `BlameEraser`, `OwnerEraser` and `VersionEraser` take the `doctrine` registry: every entity manager is reached, each
  table once per connection.
- `SoftDeleteListener` listens to `onFlush` at the priority `-256`, after the listeners of the default priority.
- The service `Phil\DoctrineBehaviours\BinaryKeysListener`, of `phil/doctrine-behaviours`, replaces
  `Infrastructure\Doctrine\BinaryKeysListener`; its fields and its effect are the same.
- `phil/doctrine-behaviours` 0.3 is required; `doctrine/dbal` and `doctrine/orm`, which the bundle's own classes no
  longer name, come with it and are only required in development.

### Removed

- `Infrastructure\Doctrine\BinaryKeysListener`: the listener is `Phil\DoctrineBehaviours\BinaryKeysListener`, so
  that an application without the bundle registers it too.

### Security

- An erasure left the personal fields of a record in the frozen versions, and going back to a version could put them
  back on the erased record.
- The erasers reached the default entity manager only, and an entity loaded before the erasure, or cached, still gave
  the old name.
- `BlameListener` wrote the name of the authenticated user, often an e-mail address, in `updated_by` during an erasure
  that flushed entities, after `BlameEraser` had replaced it.
- `SoftDeleteListener` could run before the index of `phil/search-bundle`, which then saw an update instead of a
  deletion and kept the soft-deleted record in the index.

## 0.2.0-alpha - 2026-11-18

The keys the behaviours write compare byte by byte on MySQL and MariaDB. See [Collation of the keys](doc/configuration.md#collation-of-the-keys).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, registered whichever
  behaviours are on, and its `FIELDS`: the fields that compare byte by byte on MySQL and MariaDB, by interface of the
  behaviours.

### Changed

- On MySQL and MariaDB, the slug of a `Sluggable`, the locale of a `Translation`, the owner of an `Ownable`, the blame
  of a `Blameable`, the path of a `TreeNode`, the identifier of a `DatedIdentifiable`, and `subject_table` and
  `subject_id` of `phil_record_version` take the `utf8mb4` character set and the `utf8mb4_bin` collation in the schema
  the ORM generates, where the class maps them as a string or a GUID, through
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL and SQLite keep their default, which compares bytes already.
- `doctrine/dbal` and `doctrine/orm` are required, already required by `phil/doctrine-behaviours`: `BinaryKeysListener`
  listens to the ORM and reads the platform of the connection.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the slug `Doc-A` was found for `doc-a` and the
  unique slug refused the second, the translation `fr-CA` was read for `fr-ca`, and the records of the owner `Ann`
  were listed and erased for `ann`.

## 0.1.0-alpha - 2026-11-15

### Added

- `SecurityBlameResolver`: the identifier of the authenticated user, or nobody; a user who acts as another through
  `switch_user` is blamed, not the user impersonated; `blame.user_method`, a method of the user class that names them
  in the blame, such as a UUID getter.
- `PhilDoctrineBehavioursBundle` and the `phil_doctrine_behaviours` configuration: `timestamps`, `blame` (an array
  that can be disabled, `blame: false` and `blame: true` also read), `soft_delete` (with `filter`, the
  `phil_soft_deleted` filter added to the default entity manager, enabled) and `tree`.
- `SecurityOwnerResolver`: the authenticated user as owner, by the method `owner.user_method` names, required; under
  `switch_user`, the account acted as.
- `SecurityVersionRestoreGuard`: going back to a past version takes the right `versions.restore_right`, asked of Symfony
  Security with the record as subject.
- `position`, on by default: `PositionListener` and the public service `Positions`, each group `positionGroup()` names
  ordered on its own.
- `translation`, on by default: `TranslationListener`, which gives each translation table its unique index on the record
  and the locale, and the public service `Translations`.
- `BlamePrivacy` and `OwnerPrivacy`: the blame and the owner in the access and erasure requests of
  `phil/privacy-bundle`, registered when it is installed and the behaviour is on; `privacy` in the configuration, with
  `blame_replacement` and `owner_replacement`, the pseudonyms checked when the container is built.
- `deactivation` (with `filter`, the `phil_deactivated` filter added to the default entity manager, enabled), `owner`,
  `publication` (with `workflow` and `define_workflow`, the state machine declared for `workflow.registry`), `slug`,
  `versions` (the mapping of `RecordVersion` and the public service `Versions`) and `dated_identifier`, in the
  `phil_doctrine_behaviours` configuration; a clear error for what a behaviour lacks; a slug unique in its table or
  within the scope `slugScope()` names.
- The instants of the behaviours taken from the Symfony `clock` service, written in UTC in the column types the library
  declares.
- `BehaviourServices`: the listeners, tagged on the Doctrine events each needs, the blame resolver and `Tree`;
  `TreeListener` is tagged `kernel.reset`; the public service `BlameEraser`, with `blame`.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
