# Installation

```sh
composer require phil/outbox-bundle
```

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_outbox.yaml`. Without it:

```php
// config/bundles.php
Phil\OutboxBundle\PhilOutboxBundle::class => ['all' => true],
```

| Requirement | Version |
|---|---|
| PHP | 8.5 or later, with `mbstring` and `sodium` |
| Symfony | 8.2 or later: `config`, `dependency-injection`, `clock` |
| Doctrine DBAL | 4.5 or later, through DoctrineBundle |
| `symfony/console` | for the commands |
| `doctrine/orm` | optional, to add the table to the schema the ORM generates |
| `symfony/lock` | optional, for one run of the relay at a time |
| `symfony/scheduler` and `symfony/messenger` | optional, for the scheduled trigger |
| `symfony/messenger` | optional, for the bridge to a bus |

## The table

The records live in one table, `phil_outbox` by default, on the DBAL connection of the configuration: the connection
of the tables of the libraries that record, so that a record joins their transaction.

With the ORM, the bundle adds the table to the schema the ORM generates for that connection: `doctrine:migrations:diff`
writes the migration that creates it, and `doctrine:schema:validate` keeps it. Without the ORM, or before the first
migration:

```sh
php bin/console phil:outbox:schema          # shows the SQL
php bin/console phil:outbox:schema --force  # creates the table
```

The identifier, the type and the keys compare byte by byte: the `ascii_bin` collation on MySQL and MariaDB, `C` on
PostgreSQL. Moments are kept as seconds since the Unix epoch.

## The relay

Run `phil:outbox:relay` under a process manager, or enable the scheduled trigger: see [Workers](workers.md).
