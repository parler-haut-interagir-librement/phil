# Tenants

In a multi-tenant application, each tenant has its own space `main`, its own members and its own items, often under the
same identifiers as another tenant's. The bundle keeps the tuples of each tenant in a partition of their own, so that
an admin, a member, a share or a group of one tenant never grants anything in another.

## The partition

| `phil_access.partition` | The tuples are |
|---|---|
| `auto`, the default | Partitioned by tenant when `PhilTenancyBundle` is enabled; in one partition otherwise. |
| `tenant` | Partitioned by the active tenant of `phil/tenancy-bundle`. The bundle must be enabled. |
| `single` | In one partition, whatever bundles are enabled. |

With the tenancy bundle, `Infrastructure\Tenancy\TenancyTupleScope` reads the tenant its `TenantContext` holds: the one
the request resolved, the message's stamp carries, or the command line gives with `--tenant`. Every check, listing,
share, membership and purge then acts in that tenant alone. Another tenancy layer aliases
`Phil\ResourcePermissions\Contract\TupleScope` to its own scope; see
[the partitions of `phil/resource-permissions`](https://github.com/parler-haut-interagir-librement/resource-permissions/blob/main/doc/partitions.md).

A tenant reference is the partition's name as it is: 1 to 128 bytes. A longer reference is refused when the tuples are
read or written.

## Outside any tenant

Partitioned by tenant, the tuples are neither read nor written outside a tenant: the store throws `UnscopedTuples`.
A check then fails rather than answer for no tenant in particular, and a voter that meets it lets the request fail.
Give each command and each message handler its tenant:

```sh
bin/console phil:access:purge --tenant=retail
```

`phil:access:purge` without a tenant says so and fails, deleting nothing.

## Purging every tenant

`--all-tenants` purges the expired tuples of every tenant in one run, the form a scheduled purge takes:

```sh
bin/console phil:access:purge --all-tenants
```

The command reads the partitions that hold tuples from `Application\Contract\TuplePartitions`, then, for each one in
turn, enters the tenant of that name through the `TenantContext` of `phil/tenancy-bundle`, purges its partition, and
leaves it. Every statement still names one partition: no purge runs outside a tenant or across tenants. The tenant is
left after its purge whether the purge succeeded or failed, an activation that fails on entering included, so that a
failure never carries one tenant's Doctrine filter, connection or cache namespace into the next.

A table reports each tenant: the expired tuples deleted, and `purged`, or `failed:` with the error's message, printed as
text. A tenant that fails does not stop the others; the command then exits with `1`, after purging every tenant it
could. A partition whose name is no valid tenant reference is reported as a failure and never entered. Without any
partition holding tuples, the command says so and succeeds.

| The command | Answer |
|---|---|
| The tuples are not partitioned by tenant: `phil/tenancy-bundle` disabled, or `partition: single` | Refused, exit `1`: the option needs the partitions of tenants |
| `--all-tenants` with `--tenant` | Refused, exit `1`: give one or the other |
| No `TuplePartitions` service: the tuples in memory, or in a store of the application's own | Refused, exit `1`: alias the port to a service that lists the partitions |

With the DBAL store, `Infrastructure\Dbal\DbalTuplePartitions` lists them: one `SELECT DISTINCT` on the `scope` column
of the tuple table, which leads its primary key, without the default partition. It reads the names only, never a
tuple. On a large table, PostgreSQL may read the whole index of that key for it, where MySQL skips through it; at one
run a day this is the cheap part of the purge. An application whose tenants are known elsewhere — a tenant registry —
aliases the port to a service of its own that returns the partitions, by name, each once.

## Upgrading from 0.1

The tuple table of `phil/resource-permissions` 0.2 has a `scope` column, first in its key and indexes. With the ORM,
`doctrine:migrations:diff` writes the migration, through the bundle's schema listener; the rows already there move to
the default partition, the one `partition: single` reads. A multi-tenant application then moves each tenant's rows to
the tenant's partition before it turns `partition` to `tenant`, or lets `auto` do so by enabling the tenancy bundle.
The steps, and the statements without the ORM, are in
[Upgrading from 0.1](https://github.com/parler-haut-interagir-librement/resource-permissions/blob/main/doc/partitions.md#upgrading-from-01).
