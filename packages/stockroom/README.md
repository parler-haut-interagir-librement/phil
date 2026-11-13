# phil/stockroom

**A props stockroom: nested containers, items, a journal of every movement, and per-event load lists.**

phil/stockroom keeps what a troupe, a convention or a school owns and lends: containers nested in one another — a
room, a shelf, a crate —, items counted as a quantity of interchangeable pieces or followed one by one, and the
balance of each item in each place. Every change is a movement written to a journal: stock comes in, goes out to an
event or a holder, moves between containers, is lost, or comes back. A movement never takes more than a place holds; a
tracked unit is in one place at a time; an item under repair or retired does not leave.

An event has a load list. The library says where to pick each line, deploys the list to the event — all or nothing,
or as far as the stock goes —, and checks it at the return: what was counted goes back home, what is missing is
listed and stays out until it comes back or is written off. Load lists are read from and written to plain text, one
line per item.

It is a plain PHP library: its stores are ports, with adapters in memory; moments come from a PSR-20 clock and are kept
in UTC. Another package reads the stock through the `StockReader` port without moving it — a rental offering what is
in stock, a library whose copies are items of the stockroom.

## Status

| Part | State |
|---|---|
| `Container`, `Item`, `Place`, `Movement`, `Holding`, the identifiers and their bounds | Implemented and tested |
| `Stockroom`: containers nested and moved, items, movements in, out, between containers, lost and back | Implemented and tested |
| `Deployments`: load lists, picking, deployment, the check at the return | Implemented and tested |
| `LoadListReader`, its fuzz target | Implemented and tested |
| The ports and their adapters in memory, reset between requests | Implemented and tested |
| `StockReader`, the read port of the stock | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, wiring the stores. |
| [Stock and movements](doc/stock.md) | Containers, items, places, movements and the journal. |
| [Load lists](doc/load-lists.md) | Planning, picking, deploying, checking at the return, and the text format. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/stockroom
```

```php
use Phil\Stockroom\Deployments;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\Memory\{InMemoryBalances, InMemoryContainers, InMemoryItems, InMemoryJournal, InMemoryLoadLists};
use Phil\Stockroom\Stockroom;
use Phil\Stockroom\Tracking;
use Symfony\Component\Clock\NativeClock;

$balances = new InMemoryBalances();
$stockroom = new Stockroom(new InMemoryContainers(), new InMemoryItems(), $balances, new InMemoryJournal(), new NativeClock());
$deployments = new Deployments($stockroom, new InMemoryLoadLists(), $balances);

$stockroom->addContainer('Warehouse', id: 'warehouse');
$stockroom->addContainer('Crate 3', 'warehouse', 'C-3', 'crate-3');
$swords = $stockroom->addItem('FS', 'Foam sword', Tracking::Quantity, 'crate-3');
$stockroom->receive($swords->id, 'crate-3', 40);

$deployments->plan('tavern-scene', [new LoadLine($swords->id, 12)]);
$deployments->deploy('tavern-scene');                    // 12 swords out to the event
$check = $deployments->checkIn('tavern-scene', [$swords->id => 11]);
$check->missing;                                         // one sword missing, still out at the event
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, `psr/clock`, `symfony/uid` and `symfony/service-contracts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
