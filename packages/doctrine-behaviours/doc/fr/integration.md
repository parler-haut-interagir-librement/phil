<!-- phil-dev source-sha256=778ffc02bb66f52ad53a38cbd45a4446a9403bdc02af956ec5e862949bf6e6dd -->
# Intégration

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

$versions = new Versions($manager, $clock, $restoreGuard, $personalFields);
$positions = new Positions($manager);
$translations = new Translations($manager);
```

Enregistrez `TranslationListener` et `BinaryKeysListener` avant le chargement des premières métadonnées, pour que chaque
table de traduction ait son index et chaque clé sa collation. Enregistrez `SoftDeleteListener` après les autres
écouteurs d'`onFlush` : ceux qui s'exécutent après lui voient une suppression comme une modification — voir
[Suppression douce](soft-delete.md#lordre-des-écouteurs).

Les outils d'effacement et `SoftDeletes` prennent un gestionnaire d'entités, ou un `ManagerRegistry` pour les joindre
tous :

```php
$blame = new BlameEraser($registry);
$owners = new OwnerEraser($registry);
$versionEraser = new VersionEraser($registry, $personalFields, $clock);
$softDeletes = new SoftDeletes($registry, $clock);
```

## Collation des clés

`BinaryKeysListener` donne aux clés qu'écrivent les comportements la collation binaire de MySQL et de MariaDB, au
moment où l'ORM charge les métadonnées des classes qui les prennent, pour que le schéma que l'ORM génère, et la
migration qu'écrit `doctrine:migrations:diff`, les comparent octet par octet : le slug `Doc-A` n'est jamais trouvé pour
`doc-a` et les deux peuvent être pris, la traduction `fr-CA` n'est jamais lue pour `fr-ca`, et les enregistrements du
propriétaire `Ann` ne sont jamais listés ni effacés pour `ann`.

| Une classe qui implémente, ou qui est | Champs en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `Sluggable` | `slug` |
| `Translation` | `locale` |
| `Ownable` | `ownerId` (`owner_id`) |
| `Blameable` | `createdBy`, `updatedBy` (`created_by`, `updated_by`) |
| `TreeNode` | `treePath` (`tree_path`) |
| `DatedIdentifiable` | `id` |
| `RecordVersion`, the table `phil_record_version` | `subjectTable`, `subjectId` (`subject_table`, `subject_id`), et l'auteur de `Blameable` |

`BinaryKeysListener::FIELDS` les liste. Un champ n'est changé que là où la classe le mappe comme une chaîne ou un
GUID, comme le font les traits ; un champ que la classe mappe sous un autre type, ou ne mappe pas, garde son mappage, et
les options qu'un champ porte déjà restent, la collation s'y ajoutant. Les collations par défaut de PostgreSQL sont
déterministes, égales seulement quand les octets le sont, et SQLite compare les octets : leurs colonnes gardent la
valeur par défaut, et le mappage reste le même sur chaque plateforme. La migration des tables existantes est dans
[Mises à niveau](upgrading.md#de-02-à-03).

`PublicationListener` écoute un workflow, pas Doctrine : voir [Publication](publication.md).
