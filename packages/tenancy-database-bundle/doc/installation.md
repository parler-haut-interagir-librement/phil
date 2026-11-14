# Installation

```sh
composer require phil/tenancy-database-bundle
```

Register the bundle after `phil/tenancy-bundle`, whose tenant context and activations it builds on:

```php
// config/bundles.php
Phil\TenancyBundle\PhilTenancyBundle::class => ['all' => true],
Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle::class => ['all' => true],
```

## Requirements

- PHP 8.5 or later, with the sodium extension, which `CredentialSeal` uses.
- Symfony 8.2 or later, Doctrine DBAL 4, Doctrine ORM 3 and DoctrineBundle.
- `phil/tenancy-bundle`, configured: its Doctrine filter keeps the shared tenants apart, and its tenant context tells
  this bundle which tenant is entered.
- MySQL, MariaDB or PostgreSQL. Every database — the shared one and each dedicated one — is on the platform of the
  shared connection, and holds the same schema.

Optional:

| Package | For |
|---|---|
| `symfony/console`, `symfony/process` | The `phil:tenancy:database:*` commands; each migration runs in a process of its own |
| `symfony/framework-bundle` | The pool `phil_tenancy_database.availability`, for the suspensions kept with `availability: cache` |
| `symfony/messenger` | `RefuseUnavailableTenantMiddleware`, which retries the messages of a suspended tenant later |

## The connection

The bundle takes one Doctrine connection, `default` unless `connection` names another, and moves it from database to
database. Its URL or parameters name the **shared** database; a dedicated database is reached on the same server, with
the same account, under its own name, unless `TenantCredentials` say otherwise (see [Credentials](credentials.md)).

Keep apart, on a connection of their own that does not follow the tenant:

- the Messenger transport of Doctrine, if it stores messages in the database: its table lives in the shared database;
- the application's own store of placements, when it reads them from a table — the bundle's `dbal` stores open a
  connection of their own pinned to the shared database, or use `store_connection`;
- anything else every tenant shares, such as a session table.

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default: { url: '%env(DATABASE_URL)%' }       # follows the tenant
            platform: { url: '%env(DATABASE_URL)%' }      # always the shared database
```

## The account of the shared connection

The migrations and the creation of a database run with the account of the shared connection. To create the database
of a new tenant, it needs the right to create databases (`CREATE` on MySQL, `CREATEDB` on PostgreSQL); without it, an
operator creates the database and the command only migrates it. A request needs no more than the rights on the tables.

## The schema of each database

Every database holds the whole schema, the tenant column of each `#[TenantScoped]` entity included: a dedicated
database is migrated with the same migrations, a copied row keeps its tenant reference, and the Doctrine filter keeps
working whichever database the tenant is in. Reference data that every tenant reads, such as currencies, is seeded in
each database by its migrations.
