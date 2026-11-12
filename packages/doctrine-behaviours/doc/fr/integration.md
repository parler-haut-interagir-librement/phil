<!-- phil-dev source-sha256=426f4b2c531e67453ef7354405d8bcb7cf5e2b2f0df1865a3bae1a8835a25347 -->
# Intégration

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

Les autres comportements suivent de la même façon, chacun seulement si ses entités existent. Enregistrez
`DeactivatedFilter` comme le filtre de la suppression douce, et mappez `src/Version` avec l'`AttributeDriver` quand une
entité a des versions.

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

Enregistrez `TranslationListener` avant le chargement des premières métadonnées, pour que chaque table de traduction ait
son index.

`PublicationListener` écoute un workflow, pas Doctrine : voir [Publication](publication.md).

Chaque comportement est indépendant : n'enregistrez que les écouteurs des comportements qu'utilisent les entités. Un
`BlameResolver` propre à l'application nomme l'utilisateur d'une requête, d'une tâche ou d'une commande.
