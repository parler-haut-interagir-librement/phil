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
| `addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string` | Called by Doctrine for every entity a query touches |

The bundle registers the filter enabled on the default entity manager (`doctrine_filter`); with several managers,
the application registers it; see [installation.md](installation.md). The filter only acts while it is enabled,
so register it with `enabled: true`.

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
- **Writes are not filtered.** The filter restricts what is read. Set the tenant reference on new entities from the
  resolved tenant in the application.
