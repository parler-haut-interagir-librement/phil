# phil/commerce-pos-bundle

**A cash register for counter sales: tills, shifts, cash counts, connected terminals, layaways and sales rung up
offline.**

phil/commerce-pos-bundle sells over a counter. A register is a till with its currency and the increment its cash
rounds to: the main register of a location, the tablet at the counter, or a seller's mobile one on their phone. A
session is a shift: it opens on the float counted in the drawer and follows every cash movement (sales, refunds, paid
in, paid out, drops to the safe) to what the drawer should hold. The close counts the drawer and gives the end-of-day
report: the cash over or short, the payments by method, the tips and the number of sales; the cash-difference report
lists the counts over a period and flags the large differences. Cash rounds to the register's increment, five cents
in Canada, in the drawer only; the order, its taxes and its payment keep their exact amounts.

A card on a connected terminal is driven by the server, through one adapter per processor: the till runs no script of
the processor, and the terminals that ask for a PIN sit at the main register. A card on a terminal that is not
connected, or a cheque, is recorded with its approval. A sale can be held and finished at the main register, carry a
tip, be sold under a light preset over the core's channels, or become a layaway with a deposit, instalments and a due
date. The customer display shows the cart and nothing about the person; a loot box's real dice are entered at the
counter through the form its package provides; a cashier takes over a shared device by PIN, when the application
enables it.

It is for a shop, café or market stand that takes payment in person. The cart is the core's order and its payments
the core's payments, so a register sale sits beside the web orders of `phil/commerce-bundle`; a ticket is a sellable
of the core like any other. The register's screens, the cash drawer, the receipt printer and the scanner are the
application's, and so is who may open, refund or pay out, through Symfony's security.

It is a Symfony bundle and a module of `phil/commerce-bundle`, and refuses to load without the core. Its services
read the module's data and the core's orders and payments through Doctrine, so they are registered with
DoctrineBundle. When the connection drops, the main register keeps selling: the module's Stimulus controller keeps each
sale in the browser's IndexedDB and sends it once back online; `OfflineSaleController` imports it once, re-priced by the
server, and keeps any difference for review.

## Status

| Part | State |
|---|---|
| Registers, main and mobile, sessions, float, paid in and out, drops, count, end-of-day and cash-difference reports | Implemented and tested |
| Cash with rounding and change, recorded methods, split payments, refunds | Implemented and tested |
| Connected terminals driven by the server, the port and the fake adapter | Implemented and tested |
| Held sales finished at the main register, tips, sale presets, the customer display | Implemented and tested |
| Layaways: deposit, instalments, completion, cancellation | Implemented and tested |
| Draws entered at the counter, tickets, the cashier switch by PIN | Implemented and tested |
| Offline sales: the IndexedDB queue, the import, re-pricing on the server | Implemented and tested |
| Peripherals: cash drawer, receipt printer, scanner | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, configuring, routing, the Stimulus controller. |
| [The register](doc/register.md) | Registers, sessions, the cart, payments, rounding, the end-of-day report. |
| [Main and mobile registers](doc/devices.md) | One main register per location, a seller's sale finished at the counter, the cashier switch by PIN. |
| [Connected terminals](doc/terminals.md) | Charges driven by the server, notifications, refunds, writing an adapter, the fake terminal. |
| [At the counter](doc/counter.md) | Sale presets, the customer display, draws entered by hand, tickets, the cash-difference report. |
| [Layaways](doc/layaways.md) | Deposits, instalments, completion and cancellation. |
| [Offline sales](doc/offline.md) | The queue in the browser, the import, re-pricing and discrepancies. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-pos-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommercePosBundle\PhilCommercePosBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle` and `phil/money` 0.1; Doctrine ORM 3.7; `psr/clock` 1.0;
`symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation` and `symfony/uid` 8.2 or later;
`symfony/polyfill-php86` 1.37. The Stimulus controller needs `@hotwired/stimulus` 3, which the application provides,
served by AssetMapper and StimulusBundle; the cashier switch by PIN needs `symfony/password-hasher` and
`symfony/rate-limiter`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
