# Stock and movements

## Containers

A container is a place in the stockroom that holds items and other containers: a room, a shelf, a crate, a bag. It has
an identifier, a name, the container it sits in — none at the top — and an optional code, the short text on its label.

```php
$stockroom->addContainer('Warehouse', id: 'warehouse');
$stockroom->addContainer('Shelf B', 'warehouse', 'S-B', 'shelf-b');
$stockroom->addContainer('Crate 3', 'shelf-b', id: 'crate-3');

$stockroom->path('crate-3');            // Warehouse, Shelf B, Crate 3: what the label reads
$stockroom->moveContainer('crate-3', 'truck');  // the crate and all it holds
$stockroom->contents('warehouse', nested: true); // what the warehouse holds, and what its containers hold
```

A container sits at most 32 deep (`Stockroom::MAX_DEPTH`). It never sits in itself nor in a container it holds: a move
that would make a loop is refused, and a path that loops anyway — stores filled by hand — is refused when it is read. A
nested listing walks at most 10 000 containers and gives at most 1000 lines.

## Items

An item has an identifier, a code — unique, the text on its label and what a scanner reads —, a name, how it is
counted, its home — the container it goes back to — and its condition.

| Tracking | Meaning |
|---|---|
| `Tracking::Quantity` | Interchangeable pieces counted by place: forty foam swords |
| `Tracking::Unit` | One object followed on its own, in one place at a time: the projector with its serial number |

| Condition | May leave |
|---|---|
| `ItemCondition::Usable` | Yes |
| `ItemCondition::NeedsRepair` | No; it still moves between containers |
| `ItemCondition::Retired` | No |

A lost item is not a condition: a loss is a movement, and what is left of the item stays usable.

## Places

Stock is in a container, deployed at an event, or lent to a holder:

```php
Place::container('crate-3');
Place::event('tavern-scene');
Place::holder('loan-42');
```

A holder is named by the identifier the application gives — an account, a team, a loan —, never by a name or an
e-mail address. Every identifier and reference of the library is 1 to 128 letters, digits, dots, underscores, colons
and hyphens, starting with a letter or a digit; an `@` never fits.

## Movements

Each method writes one movement — or one per container it takes from — to the journal and changes the balances.

| Method | Kind | From | To |
|---|---|---|---|
| `receive($item, $container, $quantity)` | `In` | nowhere | a container |
| `issue($item, $place, $quantity, $from)` | `Out` | a container, or the containers holding it, its home first | an event or a holder |
| `transfer($item, $from, $to, $quantity)` | `Transfer` | a container | another container |
| `lose($item, $place, $quantity)` | `Loss` | any place | nowhere |
| `giveBack($item, $place, $quantity, $to)` | `Return` | an event or a holder | the container named, or the item's home |

Each also takes a reference — the event, the loan, the order it belongs to — and a note. A movement is refused before
anything changes when:

- the item or a container does not exist (`UnknownStock`);
- a quantity is below 1 or above 1 000 000, a value is not an identifier, or the places do not fit the kind
  (`InvalidStock`);
- the place holds fewer pieces, a tracked unit is received while in stock, or the item may not leave
  (`InsufficientStock`).

A tracked unit moves one at a time. A unit lost and found comes back in with `receive()`.

Moments come from the clock given to the stockroom and are kept in UTC: `$movement->at` is always in UTC, whatever the
clock's zone.

## Reading the stock

```php
$stockroom->holdings($item);          // every place holding pieces, in the order of their keys
$stockroom->available($item);         // the pieces in containers, none when it may not leave
$stockroom->sources($stockroom->item($item)); // the containers holding it, its home first
$stockroom->history($item, 100);      // its movements, the latest first
$stockroom->movementsOf('tavern-scene'); // the movements of an event, a loan or an order
```

`Stockroom` implements `Contract\StockReader`, the port another package reads the stock through without moving it.
