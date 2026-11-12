# Integration

```php
$config->addFilter(SoftDeletedFilter::NAME, SoftDeletedFilter::class);
$manager = new EntityManager($connection, $config);

$events = $manager->getEventManager();
$events->addEventListener([Events::prePersist, Events::onFlush], new TimestampListener($clock));
$events->addEventListener([Events::prePersist, Events::onFlush], new BlameListener($resolver));
$events->addEventListener([Events::onFlush], new SoftDeleteListener($clock));
$events->addEventListener([Events::postPersist, Events::onFlush, Events::postFlush], new TreeListener());
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

$versions = new Versions($manager, $clock, $restoreGuard);
$positions = new Positions($manager);
$translations = new Translations($manager);
```

Register `TranslationListener` before the first metadata is loaded, so that every translation table has its index.

`PublicationListener` listens to a workflow, not to Doctrine: see [Publication](publication.md).

Each behaviour stands alone: register only the listeners of the behaviours the entities use. A `BlameResolver` of
the application's own names the user of a request, a job or a command.
