# phil/commerce-returns-bundle

**Return requests, from authorization to a refund, store credit or an exchange, valued line by line.**

phil/commerce-returns-bundle handles goods that come back. A return request (an RMA) names the lines of a placed order
and a reason for each, within the shop's return window, counted from the order's placement or its last shipment. It
is authorized, sent back to the customer for information, rejected, cancelled, or expired when its goods never come,
then received with the condition of each line. The resellable units go back on the shelf, and the value is refunded to
the order's payments, given as store credit, or used to pay for an exchange. At the counter, the same steps follow one
another at once.

Each unit returned is worth its share of what its order line came to, promotions and taxes on the line included. A set
— a box sold as one product — comes back whole, never a part of it. A product of a special — a price for products
bought together — comes back at its value less the special's discount, which disappears. A restocking fee, if the
application charges one, comes off the lines the shop is not at fault for. The delivery charge comes back once per
order, as the shop's policy says; tips never do, and a unit that never came back is worth nothing. A line may come back
in part, the rest staying returnable; a rejected, cancelled or expired return frees its units.

A return gets a credit note for exactly what it gives back — part of a line, the fee, the special and the delivery
included —, numbered in the core's series of credit notes; a slip for the parcel, numbered in its own series and drawn
as a PDF; and a carrier's label through an adapter. Each step is told by an event, identifiers only.

It is for a shop that ships or sells goods a customer may bring back. It is a Symfony bundle and a module of
`phil/commerce-bundle`, and refuses to load without the core; its services read the module's data and the core's
orders and payments through Doctrine, so they are registered with DoctrineBundle. It holds no stock itself: every
service of its `Restocking` port is asked to put the resellable units back. Each module beside it plugs in on its own:
`phil/commerce-stored-value-bundle` for store credit, `phil/commerce-catalog-bundle` for sets,
`phil/commerce-promotions-bundle` for specials, `phil/commerce-logistics-bundle` for shipment dates, and
`phil/paged-media` draws the slips. An exchange is a payment of the core with the method `exchange`, taken by
`ReturnsGateway`.

## Status

| Part | State |
|---|---|
| Requests, the return window from the placement or the last shipment, quantities left, a rate limit per customer | Implemented and tested |
| Authorization, questions to the customer, rejection, cancellation, expiry, reception and inspection | Implemented and tested |
| Sets returned whole, specials undone, the delivery given back once per order | Implemented and tested |
| Restocking through the `Restocking` port | Implemented and tested |
| Refund to the payments, store credit, exchange | Implemented and tested |
| Partial credit notes, return slips as PDF, labels through a carrier adapter | Implemented and tested |
| Events of each step and of each settlement and credit note | Implemented and tested |
| An adapter of carriers' labels on `phil/commerce-logistics-bundle` | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, configuring. |
| [Returns](doc/returns.md) | The way of a return, its value — sets, specials, delivery —, restocking, refunds, store credit, exchanges, labels and events. |
| [Credit notes and slips](doc/credit-notes-and-slips.md) | The credit note of a return, its numbers and amounts; the return slip and its PDF. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the ports it uses and offers. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-returns-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle` and `phil/money` 0.1; Doctrine ORM
3.7 and DBAL 4.5; `symfony/config`, `symfony/dependency-injection` and `symfony/uid` 8.2 or later;
`symfony/event-dispatcher-contracts` and `symfony/translation-contracts` 3.6 or later. Store credit needs
`phil/commerce-stored-value-bundle`; sets, specials and shipment dates need the catalogue, promotions and logistics
modules; slips as PDF need `phil/paged-media`, and a rate limit `symfony/rate-limiter`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
