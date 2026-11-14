# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## The ports

The bundle aliases each port of `phil/stockroom` to its adapter. An application that keeps part of the stockroom
elsewhere aliases the port to its own service:

| Port | Bundle's adapter (`dbal`) | In memory (`memory`) |
|---|---|---|
| `Phil\Stockroom\Contract\Containers` | `DbalContainers` | `InMemoryContainers` |
| `Phil\Stockroom\Contract\Items` | `DbalItems` | `InMemoryItems` |
| `Phil\Stockroom\Contract\Balances` | `DbalBalances` | `InMemoryBalances` |
| `Phil\Stockroom\Contract\Journal` | `DbalJournal` | `InMemoryJournal` |
| `Phil\Stockroom\Contract\LoadLists` | `DbalLoadLists` | `InMemoryLoadLists` |

```yaml
# config/services.yaml
services:
    Phil\Stockroom\Contract\Journal: '@App\Stock\ArchivedJournal'
```

An adapter of the application keeps the contract the library's `extension-points` page gives: listings in the order of
identifiers or keys, the journal the latest first, written once.

## The services

| Service | What it is |
|---|---|
| `Phil\Stockroom\Stockroom` | The stockroom on the configured stores, dated by the configured clock; public. |
| `Phil\Stockroom\Contract\StockReader` | An alias of `Stockroom`, for a package that only reads the stock. |
| `Phil\Stockroom\Deployments` | The load lists and their deployments; public. |
| `Phil\Stockroom\LoadListReader` | The reader of load lists written as text; public. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | The transactions that lock the items they move; public. |

## The connection

The parameter `phil_stockroom.connection` holds the service id of the connection of the tables, or `null` with the store
`memory`. Another bundle reads it to tell whether its writes share the stockroom's connection, and so its transactions,
as `phil/library-bundle` does.

## Decorating

Every service is a plain Symfony service: decorate `Stockroom`'s stores to add a cache or an audit, or
`StockroomTransactions` to add a metric, with `#[AsDecorator]` or `decorates:`.
