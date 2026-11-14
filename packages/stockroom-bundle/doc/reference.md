# Reference

Every public type of the package, with its role.

## Classes

| Type | Kind | Role |
|---|---|---|
| `Phil\StockroomBundle\PhilStockroomBundle` | `final class` | The bundle: its configuration `phil_stockroom` and its services. |
| `Phil\StockroomBundle\Infrastructure\DependencyInjection\StockroomServices` | `final class` | The services of the configuration: the stores, the stockroom's services, the transactions, the schema listener and command. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables` | `final readonly class` | The six table names made of one prefix, and their definitions; `configureSchema()` adds the missing ones to a schema. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers` | `final readonly class` | `Containers` on a DBAL connection. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalItems` | `final readonly class` | `Items` on a DBAL connection; codes unique. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances` | `final readonly class` | `Balances` on a DBAL connection; a quantity of zero deletes its row. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal` | `final readonly class` | `Journal` on a DBAL connection, written once, read the latest first. |
| `Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists` | `final readonly class` | `LoadLists` on a DBAL connection; a list replaced in one transaction. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | `final readonly class` | `run(Closure $work, string ...$items)`: one transaction, the items locked first, joining the caller's; `lockStatement()`; `uses(Connection)`. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomSchemaListener` | `final readonly class` | Adds the tables to the schema the ORM generates for the store's connection. |
| `Phil\StockroomBundle\Infrastructure\Dbal\Row` | `final readonly class`, internal | A row read back with its types; moments as microseconds in UTC. |
| `Phil\StockroomBundle\Infrastructure\Console\SchemaCommand` | `final readonly class` | `phil:stockroom:schema`. |

## Constants

| Constant | Value | Meaning |
|---|---|---|
| `StockroomTables::DEFAULT_PREFIX` | `phil_stockroom_` | The default prefix of the tables. |
| `StockroomTables::PREFIX_PATTERN` | `/^[a-z_][a-z0-9_]{0,39}$/D` | The shape of a prefix. |
| `StockroomTables::KEY_LENGTH` | `140` | The length of an identifier or place key column. |
| `StockroomTransactions::MAX_ITEMS` | `500` | The most items one transaction locks. |
| `StockroomServices::STORE_DBAL`, `STORE_MEMORY` | `dbal`, `memory` | The values of `store`. |
| `StockroomServices::CONNECTION` | `phil_stockroom.connection` | The parameter of the connection's service id. |

## Services and parameters

| Id | What it is |
|---|---|
| `Phil\Stockroom\Stockroom`, `Phil\Stockroom\Deployments`, `Phil\Stockroom\LoadListReader` | Public services of the library on the configured stores. |
| `Phil\Stockroom\Contract\StockReader` | Public alias of `Stockroom`. |
| `Phil\Stockroom\Contract\Containers`, `Items`, `Balances`, `Journal`, `LoadLists` | Aliases of the configured adapters. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions` | Public. |
| `Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables` | The tables of the configured prefix. |
| `phil_stockroom.connection` | Parameter: `doctrine.dbal.<connection>_connection`, or `null` in memory. |

## Command

| Command | What it does |
|---|---|
| `phil:stockroom:schema [--force]` | Shows the SQL that creates the stockroom's tables missing from its connection; runs it with `--force`. Registered with the store `dbal` when Console is installed. |

## Exceptions

| Exception | When |
|---|---|
| `InvalidArgumentException` | A table prefix that is not one; a transaction that names more than 500 items. |
| `Phil\Stockroom\Exception\InvalidStock` | A row that is not what the library keeps. |
| `UnexpectedValueException` | A column of the wrong type, or a kind, tracking or condition that is not one. |
| `Doctrine\DBAL\Exception` | The database fails; `UniqueConstraintViolationException` for a code carried by two items, or a movement written twice. |
