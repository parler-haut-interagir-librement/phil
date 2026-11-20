# Upgrading

## From 0.1 to 0.2

0.2 closes two paths between tenants in a shared database: Doctrine's identity map, which answered `find()`,
`getReference()` and lazy associations before the filter, and a flush that wrote an entity of another tenant. Nothing
in the configuration changes, but code that switches tenants inside one unit of work behaves differently.

### Flush before switching tenants

A change of tenant — `TenantContext::enter()`, `leave()` or `within()`, or the request, message or command entering
its tenant — detaches the tenant-scoped entities of every entity manager whose filter is enabled. A change made to a
loaded entity is only scheduled at the flush: if the tenant changes first, the entity is detached and its change is
lost, without an error.

```php
// 0.1: the change was written by the flush that came later, under whatever tenant was active.
$order->ship();
$context->within($wholesale, $work);
$entityManager->flush();

// 0.2: flush the tenant's work before leaving it.
$order->ship();
$entityManager->flush();
$context->within($wholesale, $work);
```

An entity scheduled for insertion (`persist()` of a new entity) or deletion (`remove()`) is not detached: its write
waits for the next flush made while its own tenant is active.

### Load entities again after `within()`

An instance loaded before `within()` is no longer managed once the work returns to the tenant before. Read it again
through its repository or `find()`: a change made to the old instance is not tracked.

```php
$order = $entityManager->find(Order::class, 7);
$context->within($wholesale, $work);
$order = $entityManager->find(Order::class, 7);   // managed again, loaded under the tenant before
```

The same holds inside the work: an entity of the outer tenant is not found from the inner one, and an entity loaded
inside the work is detached when it returns.

### `CrossTenantWrite`

`TenantWriteGuard` refuses a flush that would insert, update or delete a tenant-scoped entity of another tenant than
the one set on the filter, and throws `Phil\TenancyBundle\Infrastructure\Exception\CrossTenantWrite`; nothing is
written. It shows code that sets the tenant reference of a new entity from somewhere else than the resolved tenant, or
that flushes a pending insertion after a switch.

- Set the tenant of a new entity from the active tenant, `TenantContext::require()` or `#[CurrentTenant]`.
- Flush each tenant's writes while that tenant is active, as above.
- Work that writes across tenants on purpose — a migration, an import, an administration command — runs without a
  tenant, or with the filter disabled or suspended (see [Design decisions](doctrine-filter.md#design-decisions)); the guard lets it
  write.

A unit of work in no tenant, such as a fixture or a command run without `--tenant`, writes as before.

### Activations of the application

`TenantContext::enter()` now deactivates every activation when one fails, and throws the failure again: the unit of
work is left in no tenant rather than half in the new one. An activation of the application's own must accept
`deactivate()` after an `activate()` that failed, or that was never called.
