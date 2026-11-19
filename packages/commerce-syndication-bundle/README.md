# phil/commerce-syndication-bundle

**Resell another shop's products as your own, with orders forwarded to the supplier who ships.**

phil/commerce-syndication-bundle links separate commerce applications. A supplier holds the stock and ships; it feeds
each reseller a list of SKUs with their price, cost, stock and withdrawal. A reseller sells those products as its own
and forwards the syndicated lines of each order to their supplier, once. The supplier tells the reseller each parcel
it ships, with its carrier and tracking number; the reseller sends returns on to the supplier, which credits what it
accepts. A ledger keeps what each partner owes the other per order and per return, its balance and its settlement.
The module does not move money: the partners settle the way they agreed. It does not send descriptions or images
beyond a name, and does not send a reseller the whole feed when it is first fed.

It is for applications that trade with one another. An application can be a supplier to some partners and a reseller
of others; a single shop that sells only its own catalogue needs `phil/commerce-bundle` alone. Each partner runs on
its own, with its own database, and they talk only through [Standard Webhooks](https://www.standardwebhooks.com/)
signed with Ed25519, through `phil/standard-webhooks`. Each partner is known by its code, its https endpoint and its
public key. A message is verified with the key of the partner it claims to come from, must be addressed to this
application, and is applied once. Outgoing messages wait in an outbox, sent by the
`phil:commerce-syndication:deliver` command, and a failed attempt waits twice as long as the one before. A supplier
checks each forwarded line against the costs it published to that reseller, and both sides compute a return's credit
from their own ledger. The messages of parcels and returns carry SKUs, quantities and amounts, never a person; the
address of a forwarded order leaves its message once the work is done, and `phil:commerce-syndication:purge` deletes
messages past their retention.

It is a Symfony bundle and a module of `phil/commerce-bundle`, and refuses to load without the core. With
DoctrineBundle it stores partners, messages, syndicated products and the ledger; without it, the application declares
the services with its own adapters. Syndicated products are sellables of the core. Ports of its own keep the edges
open: `MessageTransport` carries a signed message, over Symfony's HTTP client by default; `ForwardedOrderHandler` is
what the supplier does with an order a reseller forwarded; `PartnerShipmentHandler` and `PartnerReturnHandler` are
what each side does as parcels and returns move.

## Status

| Part | State |
|---|---|
| Partners, keys, roles | Implemented and tested |
| Product feed (`product.updated`) | Implemented and tested |
| Syndicated products sold as the core's sellables | Implemented and tested |
| Order forwarding (`order.forwarded`) | Implemented and tested |
| Ledger and settlement | Implemented and tested |
| Outbox, signing, delivery with retries | Implemented and tested |
| Supplier-side price history, retention of messages | Implemented and tested |
| Shipment tracking (`shipment.sent`) | Implemented and tested |
| Returns and their credit (`return.requested`, `return.received`) | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, configuring, routing, scheduling. |
| [Syndication](doc/syndication.md) | Partners, the feed, forwarding, parcels, returns, the ledger, the messages and their delivery. |
| [Messages](doc/messages.md) | The envelope, its signature, and the data of each message type. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-syndication-bundle symfony/http-client
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_commerce_syndication.yaml
phil_commerce_syndication:
    identity: shop
    signing_key: '%env(SYNDICATION_SIGNING_KEY)%'
```

## Requirements

PHP 8.5 or later; `phil/commerce-bundle`, `phil/standard-webhooks`, `phil/ddd-bundle` and
`phil/money` 0.1; Doctrine ORM 3.7 and DBAL 4.5; `psr/clock` 1.0; `symfony/config`, `symfony/dependency-injection`,
`symfony/http-foundation` and `symfony/uid` 8.2 or later; `symfony/polyfill-php86` 1.37. `ext-sodium` comes with
`phil/standard-webhooks`; `symfony/http-client` and `symfony/console` are suggested.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
