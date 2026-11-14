# phil/stockroom-bundle

**The stockroom of `phil/stockroom` kept in the database: six tables of a Doctrine DBAL connection, movements locked
to their items, and the services configured.**

phil/stockroom-bundle brings `phil/stockroom` into a Symfony application. The library keeps no database: it names five
storage ports — the containers, the items, the balances of each place, the journal of the movements, the load lists of
the events — and ships adapters in memory. This bundle implements each port on a Doctrine DBAL connection, in six tables
under one prefix that join the schema the ORM generates, so that `doctrine:migrations:diff` writes the migration that
creates them; `phil:stockroom:schema` shows or creates them in an application without the ORM. The journal is written
once: its adapter inserts and never changes a movement.

`StockroomTransactions` runs the calls of the stockroom in one transaction, the rows of the items they move locked
first, so that a movement and its balances are written together and two movements of one item never write over each
other. When the caller has a transaction open on the same connection — the loans of `phil/library-bundle` on the same
database —, the movement joins it and is kept with the caller's writes or not at all.

It is for any Symfony application that keeps props, costumes, games or equipment in a stockroom and moves them to
events, holders and back: a larp association, a theatre, a game library, a club that lends its gear.

It is built on what Symfony and Doctrine already ship: Config and DependencyInjection for the configuration, Doctrine
DBAL for the tables and their schema, the ORM's `postGenerateSchema` event for migrations, Console for the command.

## Status

| Part | State |
|---|---|
| DBAL adapters of the five ports: containers, items, balances, journal written once, load lists | Implemented and tested on SQLite, MySQL 8.4 and PostgreSQL 17 |
| The tables in the schema the ORM generates, and `phil:stockroom:schema` | Implemented and tested |
| `StockroomTransactions`: one transaction, items locked in order, joining the caller's | Implemented and tested |
| Configuration: the store, the connection, the table prefix, the clock; the stores in memory for tests | Implemented and tested |
| `Stockroom`, `Deployments`, `LoadListReader` and `StockReader` as services | Implemented and tested |
| Pages, forms or an administration of the stockroom | Not started: the application writes its own on the services |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the recipe, the tables and their migration. |
| [Configuration](doc/configuration.md) | Every key of `phil_stockroom`, with an example. |
| [Storage](doc/storage.md) | The six tables, their columns and indexes, the collations, the journal written once, the transactions and their locks. |
| [Extension points](doc/extension-points.md) | The ports to replace, the services to use, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and testing an application on the stockroom. |
| [Reference](doc/reference.md) | Every class, service, parameter and command of the bundle. |

## Quick start

```sh
composer require phil/stockroom-bundle
bin/console doctrine:migrations:diff   # the six tables
bin/console doctrine:migrations:migrate
```

```php
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;

final readonly class Restock
{
    public function __construct(private Stockroom $stockroom, private StockroomTransactions $transactions)
    {
    }

    public function __invoke(string $item, string $shelf, int $quantity): void
    {
        $this->transactions->run(fn () => $this->stockroom->receive($item, $shelf, $quantity), $item);
    }
}
```

## Requirements

PHP 8.5 or later, `phil/stockroom`, `doctrine/dbal` 4.5 or later, and the Symfony 8.2 components Config and
DependencyInjection. DoctrineBundle for the connections, `doctrine/orm` for the tables in the generated schema,
`symfony/console` for `phil:stockroom:schema` — each only when used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
