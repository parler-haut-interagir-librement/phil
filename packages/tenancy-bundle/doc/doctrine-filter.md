# The Doctrine filter

`Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter` is a Doctrine ORM `SQLFilter` that restricts every
entity marked `#[TenantScoped]` to the active tenant, and leaves every other entity alone.

## Marking an entity

`Phil\TenancyBundle\Domain\Attribute\TenantScoped` is a class attribute naming the field that holds the tenant
reference. Its default is `tenant`; an empty name throws `InvalidValue`.

By a mapped field holding the reference:

```php
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[TenantScoped(field: 'tenantRef')]
class PriceList
{
    #[ORM\Column(name: 'tenant_ref', length: 255)]
    private string $tenantRef;
}
```

By a single-column association to the application's tenant entity:

```php
#[ORM\Entity]
#[TenantScoped] // field: 'tenant'
class Order
{
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'reference', nullable: false)]
    private Tenant $tenant;
}
```

The filter compares the **column** with the tenant reference (`TenantId::$value`), always as a quoted string. With
an association, the join column must therefore hold the tenant reference itself: reference the tenant entity's
reference column, or make that reference its identifier.

## The tenant column's collation

`TenantId` compares references byte for byte: `acme`, `ACME` and `acmé` are three tenants. The database compares
the column by its collation, and the default one of MySQL 8 and MariaDB (`utf8mb4_0900_ai_ci`,
`utf8mb4_general_ci`, …) ignores case and accents: there, `tenant_ref = 'acme'` also reads the rows of `ACME` and
`acmé`, and one tenant sees another's. Give every tenant column, and the tenant entity's reference column, a binary
collation:

```php
#[ORM\Column(name: 'tenant_ref', length: 255, options: ['collation' => 'utf8mb4_bin'])]
private string $tenantRef;
```

```php
// The tenant entity, whose reference the join columns hold.
#[ORM\Column(length: 255, unique: true, options: ['collation' => 'utf8mb4_bin'])]
private string $reference;
```

On PostgreSQL, the default collation compares case and accents already; a column given a non-deterministic ICU
collation needs `C` instead. SQLite compares text byte for byte by default. An application whose references are
lower-case slugs or UUIDs by construction is not exposed, but the collation costs nothing and holds when that
changes.

## What the filter adds

| Entity | Tenant set on the filter | SQL added |
|---|---|---|
| Not marked, no marked subclass | either | nothing |
| Marked | yes | `<alias>.<column> = '<reference>'`, the value quoted by the connection |
| Marked | no | `1 = 0`: the entity matches nothing |
| Marked, field neither mapped nor a single-column association | either | `MisconfiguredTenantScope` |
| Not marked, but a subclass is marked | either | `MisconfiguredTenantScope` |

## Setting the tenant

The bundle sets it: `DoctrineTenantActivation` follows `TenantContext`, so the tenant the request listener, a
message's stamp or `--tenant` enters is set on the filter of every entity manager where it is enabled, and leaving
the tenant enables the filter afresh, with no tenant, on every entity manager that knows it — one whose filter was
disabled for cross-tenant work and left so included: the next request of a long-running worker never starts
unfiltered. By hand, outside the context:

```php
$filter = $entityManager->getFilters()->getFilter(ActiveTenantFilter::NAME);
\assert($filter instanceof ActiveTenantFilter);
$filter->activate($resolution->tenant());
```

| Member | Role |
|---|---|
| `const NAME = 'phil_tenancy'` | The name to register the filter under |
| `const PARAMETER = 'tenant'` | The filter parameter `activate()` sets |
| `activate(TenantId $tenant): void` | Sets the active tenant; call it once the tenant is resolved, before the first tenant-scoped query of the request |
| `tenant(): ?string` | The reference set on this filter, `null` while it matches nothing |
| `addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string` | Called by Doctrine for every entity a query touches |

The bundle registers the filter enabled on the default entity manager (`doctrine_filter`); with several managers,
the application registers it; see [installation.md](installation.md). The filter only acts while it is enabled,
so register it with `enabled: true`.

## The identity map

Doctrine answers `find()`, `getReference()` and a lazy association from its identity map before any query: an entity
already loaded is returned as it is, and the filter never sees the call. In a shared database, an entity loaded while
one tenant is active would be found again after the switch to another. `DoctrineTenantActivation` closes that path:
when the tenant of an entity manager whose filter is enabled changes — another tenant entered, or the tenant left — it
detaches the tenant-scoped entities the manager holds.

```php
$order = $entityManager->find(Order::class, 7);                    // in the tenant "retail"

$context->within(TenantId::fromString('wholesale'), static fn (): ?Order => $entityManager->find(Order::class, 7));
// null: the order of "retail" is detached, and the filter of "wholesale" does not match its row

$order = $entityManager->find(Order::class, 7);                    // back in "retail": loaded again
```

| Change | Tenant-scoped entities | Shared entities |
|---|---|---|
| The same tenant entered again | kept | kept |
| Another tenant entered | detached | kept |
| The tenant left | detached | kept |
| No tenant left, the filter carrying none | kept | kept |

An entity scheduled for insertion or deletion is never detached, so that its write is not dropped silently: the write
guard below refuses to flush it under another tenant, and it is written once its own tenant is active again. A change
to a loaded entity is not scheduled before the flush: **flush before switching tenants**, and load an entity again
after `TenantContext::within()` rather than keep the instance loaded before.

## The write guard

The filter restricts reads. `TenantWriteGuard`, which the bundle registers on `onFlush` while `doctrine_filter` is on,
refuses a flush that would insert, update or delete a tenant-scoped entity of another tenant than the one set on the
filter of the entity manager that flushes: the flush throws `CrossTenantWrite`, and nothing is written.

| Unit of work | Entity of the active tenant | Entity of another tenant, or of none |
|---|---|---|
| A tenant set on the enabled filter | written | `CrossTenantWrite` |
| No tenant on the filter: a migration, a fixture, a command run without `--tenant` | written | written |
| The filter disabled or suspended: work across tenants | written | written |

The tenant of an entity is read from the field its attribute names: the reference held in a mapped field, or the
identifier of the tenant entity a single-column association points to (`EntityTenant::of()`). Shared entities are left
alone. The guard is the last line in a shared database without row-level security; the application still sets the
tenant reference of a new entity from the resolved tenant.

## Design decisions

- **Fail closed.** Without an active tenant, a tenant-scoped entity matches nothing: a request that forgot to set
  the tenant sees no tenant's rows, rather than every tenant's.
- **Cross-tenant work disables the filter explicitly**, where a reviewer can see it: a console command, a report,
  a migration of data.

  ```php
  $filters = $entityManager->getFilters();
  $filters->suspend(ActiveTenantFilter::NAME);
  try {
      // work across tenants
  } finally {
      $filters->restore(ActiveTenantFilter::NAME);
  }
  ```

  Doctrine's `suspend()` and `restore()` keep the filter instance and its tenant. `disable()` followed by
  `enable()` creates a fresh instance with no tenant, which matches nothing until `activate()` is called again.
- **A scope the filter cannot apply is a mapping error**, raised on the first query that touches the entity, even
  while no tenant is set, rather than ignored. An entity the filter skipped would be visible to every tenant.
- **The attribute belongs on the inheritance root.** Doctrine hands the filter the root class of a hierarchy, so
  an attribute declared only on a subclass would never be read and the subclass's rows would be shown to every
  tenant. The filter refuses that case instead of ignoring it. An attribute on the root applies to the whole
  hierarchy.
- **The value is quoted by the connection**, never concatenated into the SQL.

## `MisconfiguredTenantScope`

`Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope` extends `LogicException`: it is a programming
error, not a runtime condition to catch.

| Named constructor | Message |
|---|---|
| `forField(string $entity, string $field)` | The tenant-scoped entity names a field that is neither a mapped field nor a single-column association |
| `onSubclass(string $root, string $subclass)` | The entity is tenant-scoped but its inheritance root is not; the scope belongs on the root |

## Limits

- **DQL and the ORM only.** A filter applies to queries the ORM builds; a query through DBAL (`Connection`) or
  native SQL is not filtered. Add the tenant condition by hand there.
- **One tenant at a time.** The filter compares with one reference; a query over several tenants disables it.
- **Writes are guarded, not filtered.** The filter restricts what is read; the write guard refuses a flush that writes
  another tenant's entity, but writes through DBAL or native SQL are not seen by either. Set the tenant reference on
  new entities from the resolved tenant in the application, and use row-level security
  ([row-level-security.md](row-level-security.md)) for a barrier below the ORM.
