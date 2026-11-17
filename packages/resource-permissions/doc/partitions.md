# Partitions

A store keeps its tuples in partitions: one per tenant of a multi-tenant application, or the default partition alone.
Every call of a store reads, writes, forgets and purges in one partition, the one its `Contract\TupleScope` names at
that moment, and never sees another. Two tenants may then use the same identifiers — `space:main`, `doc:42`,
`member:ann` — without a tuple of one ever granting anything on the other's resource of the same name.

## The scope

```php
interface TupleScope
{
    public function partition(): string;
}
```

| Scope | Partition |
|---|---|
| `SinglePartition` | `Partition::DEFAULT`, the empty name: every tuple in one partition. A store built without a scope uses it. |
| `FixedPartition` | The name it was built with: a test, an import, a job that runs for one tenant. |
| Your own | The tenant of the request, the message or the command, read on each call. |

`phil/access-bundle` brings one on the active tenant of `phil/tenancy-bundle`. Any other tenancy layer takes a few
lines:

```php
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Exception\UnscopedTuples;

final readonly class AppTenantScope implements TupleScope
{
    public function __construct(private AppTenants $tenants)
    {
    }

    public function partition(): string
    {
        return $this->tenants->current()?->id ?? throw UnscopedTuples::because('No tenant is active.');
    }
}

$store = new DbalTupleStore($connection, $clock, scope: new AppTenantScope($tenants));
```

A store asks the scope on every call, before any statement, so a service built once serves every tenant of a
long-running worker. Keep the scope free of state of its own: read the tenant from where the request, the message or
the command put it.

## Failing closed

In a multi-tenant application, a call made outside any tenant must not fall back to a partition: the scope throws
`Exception\UnscopedTuples`, and the store reads and writes nothing. A check then fails instead of answering, a write
leaves the table as it was, and `purgeExpired()` deletes nothing. Give a command its tenant before it touches the
tuples, and a message handler the tenant of its message.

## The rule of a name

A partition is `Partition::DEFAULT` (empty), or 1 to 128 bytes of UTF-8 without control characters. Names compare byte
by byte: `Tenant-A` is not `tenant-a`. A store refuses a name against the rule with `InvalidRelation`. The 128 bytes
keep the primary key of the database store within the limit of MySQL; a tenancy layer whose references may be longer
maps them to a shorter stable name, such as a digest.

## In the database

The table has a `scope` column, first in the primary key and in each index, and every statement names it. Its default
is the empty name, so a row written without it belongs to the default partition. See [Storage](storage.md).

## Upgrading from 0.1

Version 0.1 kept the tuples without partitions. The table of 0.2 adds the `scope` column, starts the primary key and
both indexes with it, and keeps the names of kinds and relations in ASCII on MySQL and MariaDB so that the key stays
within 3,072 bytes. Every row already there moves to the default partition, by the column's default.

1. Stop the writers of the table, or run the upgrade in a maintenance window: on MySQL and MariaDB the primary key is
   dropped then added again, in statements that are not one transaction.
2. Bring the table to the new definition:
   - with the ORM and `Dbal\TupleSchemaListener`, `doctrine:migrations:diff` writes the migration;
   - otherwise, `DbalTupleStore::schemaUpdate()` gives the statements, for a migration of your own:

   ```php
   foreach ($store->schemaUpdate() as $statement) {
       $connection->executeStatement($statement);
   }
   ```

   `schemaUpdate()` creates the table when it is missing, alters it when it is older, and gives nothing when it is
   current.
3. An application with one tenant is done: its scope is `SinglePartition`, which reads the default partition.
4. A multi-tenant application moves each tenant's rows from the default partition to the tenant's own, with the
   identifiers it knows belong to that tenant — for instance, `UPDATE phil_relation_tuple SET scope = 'retail' WHERE
   scope = '' AND object_id LIKE 'retail-%'` —, then gives its stores a scope on the tenant. A row left in the default
   partition is read by nobody once every call names a tenant.
