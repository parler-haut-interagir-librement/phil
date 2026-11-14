# Configuration

The bundle's configuration lives under `phil_stockroom`. Every key has a default:

```yaml
# config/packages/phil_stockroom.yaml
phil_stockroom:
    store: dbal
    connection: default
    table_prefix: phil_stockroom_
    clock: clock
```

| Key | Default | What it does |
|---|---|---|
| `store` | `dbal` | Where the stockroom lives: `dbal`, six tables of a DBAL connection; `memory`, the adapters in memory of `phil/stockroom`, emptied between the requests of a worker — for tests and single runs. |
| `connection` | `default` | The name of the DoctrineBundle connection the tables are in: 1 to 64 letters, digits or `_`. The service is `doctrine.dbal.<name>_connection`. |
| `table_prefix` | `phil_stockroom_` | The prefix of the six table names: 1 to 40 lowercase letters, digits or `_`, not starting with a digit. It is checked when the container is built, and again before it reaches SQL. |
| `clock` | `clock` | The service id of the PSR-20 clock the movements are dated by; Symfony's clock by default. |

## The connection and the transactions

The stockroom's transactions run on the connection of its tables. Keep the tables on the connection of the
application's other writes — the default one, most often — when a movement must be kept with them: an application's
own transaction on that connection is joined by the stockroom's, and `phil/library-bundle` runs each loan and its
movement in one transaction only then. On a connection of its own, the stockroom commits its movements apart.

## In tests

```yaml
# config/packages/test/phil_stockroom.yaml
phil_stockroom:
    store: memory
```

The stores in memory hold the same contract as the tables, and `StockroomTransactions` then runs its work without a
transaction. A test of the tables themselves keeps `dbal`, on SQLite or a test server.
