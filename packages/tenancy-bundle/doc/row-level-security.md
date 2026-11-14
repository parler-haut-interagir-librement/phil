# Row-level security

The Doctrine filter restricts every query the ORM builds. It does not see native SQL, a query run with the filter
disabled by mistake, or a report written against the tables. On PostgreSQL, the bundle can repeat the rule in the
database itself, as a second barrier: **row-level security**, optional and off by default.

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    row_level_security:
        enabled: true
        connection: default        # the Doctrine connection whose sessions carry the tenant
        entity_manager: default    # the entity manager whose tenant-scoped entities get a policy
        setting: phil.tenant_id    # the session variable
        policy: phil_tenant_isolation
```

## The session variable

When the tenant context enters a tenant, `TenantSession`, one of its activations, sets the variable on every open
connection of the configured Doctrine connection:

```sql
SELECT set_config('phil.tenant_id', 'retail', false)
```

- **Each connection, as it opens.** A DBAL middleware, `TenantSessionMiddleware`, hands every new connection to the
  session, which sets the active tenant on it. A connection that reconnects in a long-running worker takes the
  tenant again.
- **Fail closed.** A connection opens with the variable unset, and leaving the tenant empties it: no policy matches.
  `TenantContext::leave()` runs at each kernel reset.
- **Bound, never interpolated.** The tenant reference is a parameter of `set_config()`: a reference shaped like SQL is
  only an unknown tenant.
- **For the session.** The variable outlives transactions, so a connection pooler must hand the same connection to
  the same client: connect to PostgreSQL directly, or through a pooler in session mode, never in transaction mode.
  Enter the tenant outside a transaction: a transaction that rolls back takes back the `set_config()` it holds.

The setting is two lowercase identifiers joined by a dot, as PostgreSQL requires of a custom variable; the policy is
one identifier. A name outside those rules stops the compilation of the container.

## The policies

```bash
bin/console phil:tenancy:rls:policies            # the statements, for the up() of a migration
bin/console phil:tenancy:rls:policies --disable  # their removal, for its down()
```

For each tenant-scoped entity — the root of its hierarchy, as the filter reads it — the command prints:

```sql
ALTER TABLE invoice ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS phil_tenant_isolation ON invoice;
CREATE POLICY phil_tenant_isolation ON invoice USING (tenant_ref::text = current_setting('phil.tenant_id', true)) WITH CHECK (tenant_ref::text = current_setting('phil.tenant_id', true));
```

- `FORCE` holds the owner of the table too, so that the application's own role is under the policy.
- `USING` filters the rows read, updated and deleted; `WITH CHECK` refuses a row written for another tenant.
- `current_setting(…, true)` is null when the variable was never set, and the comparison then matches nothing.
- The column is compared as text, as the filter compares it to the text of the tenant reference: a `uuid` column
  holds its lowercase form, an integer its digits.

Nothing is run by the command: paste the statements into a migration, `$this->addSql()` each one. Run it again
after adding a tenant-scoped entity.

## The role

PostgreSQL does not apply row-level security to a superuser, nor to a role with `BYPASSRLS`. The application connects
with a role that has neither. Work that legitimately spans tenants — migrations, fixtures, a report across tenants —
runs on another connection, with a role that has `BYPASSRLS`, where a reviewer can see it.

## The check

```bash
bin/console phil:tenancy:rls:check
```

On the database itself, the command checks that:

- the connection is PostgreSQL;
- its role is neither a superuser nor `BYPASSRLS`;
- every tenant-scoped table exists, has row-level security enabled and forced, and carries the policy, whose
  `USING` and `WITH CHECK` read the session variable.

Each problem is printed on its own line, and the command fails while there is one: run it after the migrations of a
deployment.

## What it does not cover

- Other databases: MySQL and MariaDB have no row-level security, SQLite neither.
- Tables the ORM does not map, and entities without `#[TenantScoped]`.
- The second barrier does not replace the filter: the filter still writes the tenant into every query, which keeps
  the indexes of the tenant column in use.
