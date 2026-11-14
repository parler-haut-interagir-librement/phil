# The connection of each tenant

## One connection, rewritten as it opens

`TenantDatabaseMiddleware` wraps the driver of the configured connection. Each time the connection opens, its driver
asks `TenantDatabaseRouter` for the parameters: those of the shared connection as configured, or, for a dedicated
tenant, the same parameters with the tenant's database name — and the account, host and port of `TenantCredentials`
when it gives them. Nothing is decided when the container compiles: the connection service is the one DoctrineBundle
declares, and a tenant added at runtime needs no deployment.

A primary and its replicas reach the driver one node at a time: each node opens on the tenant's database.

## Following the active tenant

`TenantConnectionSwitch` is a tenant activation of `phil/tenancy-bundle`: `TenantContext` calls it each time a request,
a message or a command enters a tenant and leaves it.

| Event | What happens |
|---|---|
| A tenant is entered | Its placement is read from `TenantPlacement`. If it is in another database than the connection, the connection is closed and routed there; it opens on the new database at the next query. |
| The same tenant is entered again | Nothing. |
| Another tenant of the same database is entered | The connection stays open; the entity managers are cleared. |
| The tenant is left | The connection returns to the shared database, where the Doctrine filter shows nothing without a tenant. |
| `kernel.reset` | The same as leaving: the next request of a worker starts in the shared database. |

The placement is read at each entry, never kept: once a tenant is moved, the next request, message or command finds it
in its new database, in every process.

## Transactions

A transaction belongs to the database it started on. Entering a tenant of another database while a transaction is open
throws `TenantSwitchInTransaction`, and the connection stays where it is: commit or roll back first. A tenant of the
same database is entered inside the transaction.

Leaving a tenant while a transaction is still open cannot be refused — it happens in a `finally` and at `kernel.reset`
— so the connection is closed with the transaction, which the server rolls back, and a warning is logged.

## The identity map

Each change of tenant clears the entity managers whose connection is the managed one. `EntityManager::find()` reads the
identity map before the database: without the clear, an entity loaded for one tenant would be handed to the next one
that asks for the same key, from whatever database. An entity manager on another connection is left alone.

Changes not flushed before a change of tenant are discarded with the identity map: flush within the tenant.

## Workers

The router and the switch keep the current database, and both are reset with the kernel: a FrankenPHP or Messenger
worker starts each unit of work on the shared database, with clean entity managers. Nothing else is kept between
requests.

## Messenger

A message carries the tenant's reference only, in the stamp of `phil/tenancy-bundle`, never a database name or
credentials. The worker enters the tenant of each received message, and the connection follows its placement as it is
when the message is handled, not as it was when it was sent. With `RefuseUnavailableTenantMiddleware`, a message of a
suspended tenant throws `TenantDatabaseUnavailable` and is retried by the transport's retry strategy.

## The cache

The cache pools of `phil/tenancy-bundle` are scoped by the tenant's reference, not by its database: a tenant that moves
keeps its cache, which still matches its data, copied as it was.

## Row-level security

For the shared database, the row-level security of PostgreSQL that `phil/tenancy-bundle` offers stays a second barrier
behind the Doctrine filter: its session variable is set on every connection the managed connection opens, dedicated or
shared, and a dedicated database may enable the same policies. The connections of a move are opened in the tenant, so
the policies let its rows through.
