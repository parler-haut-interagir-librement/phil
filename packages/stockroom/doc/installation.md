# Installation

```sh
composer require phil/stockroom
```

The package needs PHP 8.5 or later with `ext-mbstring`, `psr/clock`, `symfony/uid` for the identifiers it makes and
`symfony/service-contracts` for the reset of its stores in memory.

## Wiring the stores

`Stockroom` takes five things: the stores of the containers, the items, the balances and the journal, and a clock.
`Deployments` takes the stockroom, the store of the load lists and the balances.

```php
use Phil\Stockroom\Deployments;
use Phil\Stockroom\Stockroom;

$stockroom = new Stockroom($containers, $items, $balances, $journal, $clock);
$deployments = new Deployments($stockroom, $loadLists, $balances);
```

The package ships the stores in memory, for tests, scripts and demonstrations. An application that keeps its stock
implements the five ports of `Phil\Stockroom\Contract` on its database — see [Extension points](extension-points.md) —
and runs each call of the stockroom in a transaction, so that a movement and the balances it changes are written
together.

In a Symfony application, the services are declared like any other:

```yaml
# config/services.yaml
services:
    Phil\Stockroom\Stockroom:
        arguments: ['@App\Stock\Containers', '@App\Stock\Items', '@App\Stock\Balances', '@App\Stock\Journal', '@clock']
    Phil\Stockroom\Deployments:
        arguments: ['@Phil\Stockroom\Stockroom', '@App\Stock\LoadLists', '@App\Stock\Balances']
```

The services keep no state between two calls; the stores in memory implement `ResetInterface`, which the kernel calls
between the requests of a worker.
