# Installation

```sh
composer require phil/stockroom-bundle
```

The Flex recipe registers the bundle and writes `config/packages/phil_stockroom.yaml`. Without Flex, register it in
`config/bundles.php`:

```php
Phil\StockroomBundle\PhilStockroomBundle::class => ['all' => true],
```

## Requirements

- PHP 8.5 or later, and `phil/stockroom`.
- `doctrine/dbal` 4.5 or later, and DoctrineBundle for the connection the store `dbal` names.
- The Symfony 8.2 components Config and DependencyInjection.
- Optional: `doctrine/orm`, for the tables in the schema the ORM generates; `symfony/console`, for
  `phil:stockroom:schema`.

The tables run on PostgreSQL, MySQL, MariaDB and SQLite.

## The tables

With the ORM, the bundle's schema listener adds the six tables to the schema of the connection, as Symfony's own
database stores do:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

`doctrine:schema:validate` then keeps them in step, and a later migration carries any change to them.

Without the ORM, the command shows the statements that create the tables missing from the connection, and runs them with
`--force`:

```sh
bin/console phil:stockroom:schema           # shows the SQL
bin/console phil:stockroom:schema --force   # creates the missing tables
```

A table that is there is left as it is; the command never drops nor alters one.

## With phil/library-bundle

When both bundles are installed, the loans of linked copies move their items in this stockroom without any
configuration; see the stockroom section of `phil/library-bundle`'s documentation.
