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
| The end of a request, a command or a message | A transaction still open is rolled back, then the tenant is left (see [Workers](#workers)). |
| `kernel.reset` | The same as leaving: the next request of a worker starts in the shared database. |

The placement is read at each entry, never kept: once a tenant is moved, the next request, message or command finds it
in its new database, in every process.

## Transactions

A transaction belongs to the database it started on. Entering a tenant of another database while a transaction is open
throws `TenantSwitchInTransaction`, and the connection stays where it is: commit or roll back first. A tenant of the
same database is entered inside the transaction.

A transaction still open when a request, a command or a message ends is rolled back by `RollBackOpenTransactions`,
before the tenant is left (see [Workers](#workers)).

Leaving a tenant while a transaction is still open cannot be refused — it happens in a `finally` and at `kernel.reset`
— so the connection of a dedicated database is closed with the transaction, which the server rolls back, and a warning
is logged.

## The identity map

Each change of tenant clears the entity managers whose connection is the managed one. `EntityManager::find()` reads the
identity map before the database: without the clear, an entity loaded for one tenant would be handed to the next one
that asks for the same key, from whatever database. An entity manager on another connection is left alone.

Changes not flushed before a change of tenant are discarded with the identity map: flush within the tenant.

## Workers

A FrankenPHP worker, a Messenger worker or a long-running console serves one unit of work after another in the same
process, with the same services. The router and the switch keep the current database, and nothing else is kept between
units of work. Each unit of work ends in two steps.

### At the end: terminate

`RollBackOpenTransactions` listens to the events that end a unit of work:

| Event | When it runs |
|---|---|
| `kernel.terminate` | After the response is sent, after the application's own listeners (priority `-2048`). |
| `console.terminate` | Before the tenant of the command is left by `phil/tenancy-bundle`. |
| `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent` | After a message is handled or has failed (priority `-2048`). |

For the connection that follows the tenant, and for `phil_tenancy_database.store_connection` when the bundle declares
it:

1. A transaction still open is rolled back, as many times as it is nested, and a warning is logged. The warning names
   the tenant, the active one or the one the message was stamped with, and nothing else: no statement, row, host or
   account.
2. A connection that cannot roll back, such as one the server dropped, is closed: the server rolls back what it had
   open, and the next statement opens a new connection.
3. A connection never opened in the process is not created; one with no transaction, or closed, is left as it is.

Then the tenant is left: the connection returns to the shared database and the entity managers of the connection are
cleared. A request that throws in the middle of a transaction therefore leaves neither its writes nor its locks to the
next one, which `kernel.reset` alone would only reach when the next request starts.

A connection without autocommit begins a new transaction after each rollback: its work not committed by the end of the
unit of work is discarded.

### At the start: reset

`kernel.reset` runs as the next request starts. The router, the switch and the tenant context of `phil/tenancy-bundle`
are reset, which returns the connection to the shared database once more, and the store connection is closed, so that
a worker never reads through a connection the server dropped while it waited. The next request, message or command
starts on the shared database, with clean entity managers and no transaction.

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
