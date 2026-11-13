# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Stockroom` | Containers, items and every movement, written to the journal as it changes the balances; the `StockReader` |
| `Deployments` | The load list of each event: planning, picking, deploying, checking at the return |
| `LoadListReader` | Reads a load list written as text and writes one back |
| `Container` | A place in the stockroom that holds items and other containers |
| `Item` | Something the stockroom keeps, counted or tracked |
| `Tracking` | How an item is counted: a quantity, or one unit |
| `ItemCondition` | Usable, waiting for a repair, or retired |
| `Place`, `PlaceKind` | Where stock is: a container, an event, a holder |
| `Movement`, `MovementKind` | One line of the journal: in, out, transfer, loss, return |
| `Holding` | How many pieces of an item a place holds |
| `LoadLine`, `LoadList` | What an event takes out of the stockroom |
| `PickLine` | Where to find what a load line takes |
| `Deployment` | What a deployment did, and what it was short of |
| `ReturnCheck` | What the check at the return found: returns, missing, surplus |
| `Shortfall` | An item of which fewer pieces are there than expected |
| `Identifier`, `Text`, `Quantity` | The shapes and bounds of identifiers, names and notes, and quantities |
| `Contract\Containers`, `Contract\Items`, `Contract\Balances`, `Contract\Journal`, `Contract\LoadLists` | The ports of the stores |
| `Contract\StockReader` | The read port of the stock, for another package |
| `Memory\InMemoryContainers`, `Memory\InMemoryItems`, `Memory\InMemoryBalances`, `Memory\InMemoryJournal`, `Memory\InMemoryLoadLists` | The stores in memory, reset between requests |
| `Exception\StockroomException` | Every exception the library throws |
| `Exception\InvalidStock` | A value it cannot take |
| `Exception\UnknownStock` | A container, an item or a load list that does not exist |
| `Exception\InsufficientStock` | A movement that takes more than there is, or an item that may not move |
