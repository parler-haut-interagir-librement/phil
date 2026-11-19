# Integration

```php
$config->addFilter(SoftDeletedFilter::NAME, SoftDeletedFilter::class);
$manager = new EntityManager($connection, $config);

$events = $manager->getEventManager();
$events->addEventListener([Events::prePersist, Events::onFlush], new TimestampListener($clock));
$events->addEventListener([Events::loadClassMetadata], new BinaryKeysListener());
$events->addEventListener([Events::prePersist, Events::onFlush], new BlameListener($resolver, $suspension));
$events->addEventListener([Events::postPersist, Events::onFlush, Events::postFlush], new TreeListener());
// … every other onFlush listener, those of the application included, then:
$events->addEventListener([Events::onFlush], new SoftDeleteListener($clock));
$manager->getFilters()->enable(SoftDeletedFilter::NAME);

$tree = new Tree($manager);
```

The other behaviours follow the same way, each only if its entities exist. Register `DeactivatedFilter` like the soft
delete filter, and map `src/Version` with the `AttributeDriver` when an entity has versions.

```php
$config->addFilter(DeactivatedFilter::NAME, DeactivatedFilter::class);

$events->addEventListener([Events::prePersist, Events::onFlush], new DeactivationListener($clock));
$events->addEventListener([Events::prePersist], new OwnerListener($ownerResolver));
$events->addEventListener([Events::onFlush], new SlugListener($sluggers));
$events->addEventListener([Events::onFlush], new PositionListener());
$events->addEventListener([Events::onFlush], new VersionListener());
$events->addEventListener([Events::prePersist], new DatedIdentifierListener($clock));
$events->addEventListener([Events::loadClassMetadata], new TranslationListener());

$versions = new Versions($manager, $clock, $restoreGuard, $personalFields);
$positions = new Positions($manager);
$translations = new Translations($manager);
```

Register `TranslationListener` and `BinaryKeysListener` before the first metadata is loaded, so that every translation
table has its index and every key its collation. Register `SoftDeleteListener` after the other `onFlush` listeners:
those that run after it see a removal as an update — see [Soft delete](soft-delete.md#the-order-of-the-listeners).

The erasers and `SoftDeletes` take an entity manager, or a `ManagerRegistry` to reach every one:

```php
$blame = new BlameEraser($registry);
$owners = new OwnerEraser($registry);
$versionEraser = new VersionEraser($registry, $personalFields, $clock);
$softDeletes = new SoftDeletes($registry, $clock);
```

## Collation of the keys

`BinaryKeysListener` gives the keys the behaviours write the binary collation of MySQL and MariaDB as the ORM loads the
metadata of the classes that take them, so that the schema the ORM generates, and the migration
`doctrine:migrations:diff` writes, compare them byte by byte: the slug `Doc-A` is never found for `doc-a` and both can
be taken, the translation `fr-CA` is never read for `fr-ca`, and the records of the owner `Ann` are never listed or
erased for `ann`.

| A class that implements, or is | Fields in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `Sluggable` | `slug` |
| `Translation` | `locale` |
| `Ownable` | `ownerId` (`owner_id`) |
| `Blameable` | `createdBy`, `updatedBy` (`created_by`, `updated_by`) |
| `TreeNode` | `treePath` (`tree_path`) |
| `DatedIdentifiable` | `id` |
| `RecordVersion`, the table `phil_record_version` | `subjectTable`, `subjectId` (`subject_table`, `subject_id`), and the blame of `Blameable` |

`BinaryKeysListener::FIELDS` lists them. A field is changed only where the class maps it as a string or a GUID, as the
traits do; a field the class maps under another type, or does not map, keeps its mapping, and the options a field
already carries stay, the collation added to them. PostgreSQL's default collations are deterministic, equal only when
the bytes are, and SQLite compares bytes: their columns keep the default, and the mapping stays the same on every
platform. The migration of existing tables is in [Upgrading](upgrading.md#from-02-to-03).

`PublicationListener` listens to a workflow, not to Doctrine: see [Publication](publication.md).

Each behaviour stands alone: register only the listeners of the behaviours the entities use. A `BlameResolver` of
the application's own names the user of a request, a job or a command.
