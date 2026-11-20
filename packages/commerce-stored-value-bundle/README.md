# phil/commerce-stored-value-bundle

**Digital gift cards and store credit, sold, loaded and spent as payments of an order.**

phil/commerce-stored-value-bundle holds two kinds of balance. A gift card is sold as a sellable of the core, such as
`gift_card:CAD:5000`, and issued once the order is placed, one card per unit, with a sixteen-character code. A card can
also be given, loaded or voided. A given card may expire; a paid card never does, unless it is marked for a specific
service; each shop checks the law that applies where it sells before it sets an expiry. A store credit is one balance
per customer and currency, granted on a return or as a gesture. Either pays part or all of an order, beside a card or
cash for a split payment, and a refund gives the value back to it. Every change of a balance is a movement, kept for
good.

It is for a shop that sells gift cards or gives store credit. `phil/commerce-returns-bundle` uses it to settle a
return as store credit, and `phil/commerce-pos-bundle` counts a gift card taken at the register in its session. The
module does not send the code to the buyer, print physical cards, report unclaimed balances or limit how often a code
may be tried. Those stay with the application, for example with `symfony/rate-limiter` for the tries.

It is a Symfony bundle and a module of `phil/commerce-bundle`, and refuses to load without the core. With
DoctrineBundle it stores the cards, the credits and their movements. It plugs into two ports of the core:
`GiftCardSellables` resolves the sellable type `gift_card`, and `StoredValueGateway` takes the payment methods
`gift_card` and `store_credit`. A code is written in Crockford's Base32, read forgivingly from what a person types,
and stored only as its hash and its last four characters.

## Status

| Part | State |
|---|---|
| Gift cards: sold, issued, given, loaded, voided; given or paid, expiry for given cards and specific services | Implemented and tested |
| A card printed as a PDF the size of a payment card | Implemented and tested |
| Store credit: granted, balance | Implemented and tested |
| Payment with either, split payments, refunds back to the instrument | Implemented and tested |
| Movements, the history of every balance | Implemented and tested |
| Physical cards, code delivery, rate limiting | The application's |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, configuring. |
| [Stored value](doc/stored-value.md) | Gift cards, store credit, paying with them, refunds, movements. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the ports of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-stored-value-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle`, `phil/money` and `phil/paged-media`
0.1; Doctrine ORM 3.7; `psr/clock` 1.0; `symfony/config`, `symfony/dependency-injection` and `symfony/uid` 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
