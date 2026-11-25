# phil/commerce-wholesale-bundle

**Customer groups with their own prices, minimum order and quantity rules, and sales on credit with net terms.**

phil/commerce-wholesale-bundle sells to other businesses. A customer group has its own terms: a share off the base
prices of products and a minimum order. Each buyer belongs to one group at most, and the module prices the products of
an order for that group and tells what the order still lacks to reach the group's minimum. A SKU can be sold only by a
minimum, up to a maximum and in steps of an increment, for every buyer or for one group: a constraint of Symfony's
Validator, with its messages in English and French, checked again whenever the cart changes. A buyer can also buy on
credit: an account with a limit and terms such as net 30, each purchase recorded as a receivable due after the terms,
the buyer's payments applied to it, and what is overdue listed. The module does not send statements or reminders, and
does not charge interest or late fees.

It is for an application that sells wholesale beside its retail prices. The buyer is named as the application names
it: an account, or the tenant of a multi-tenant application buying for its own customers. A group's own price list
is a price book of `phil/commerce-catalog-bundle` that names the group; the group's share applies only where no such
price book prices the product. An application that sells only at retail needs `phil/commerce-bundle` alone, and
perhaps its subscriptions.

It is a Symfony bundle and a module of `phil/commerce-bundle`: it refuses to load without the core, and registers its
services with DoctrineBundle, which stores groups, memberships, quantity rules, accounts and receivables. It plugs into
two ports of the core. `GroupPrices` is a unit price resolver asked after the catalogue's price books, and
`QuantityGuard` one asked before every price, which refuses a quantity its rule does not allow. A purchase on account is
a payment of the core with the method `on_account`, taken by `AccountGateway`, so refunds go through the core's payments
like any other.

## Status

| Part | State |
|---|---|
| Customer groups, memberships | Implemented and tested |
| Group prices, minimum orders | Implemented and tested |
| Quantity rules per SKU and group: minimum, maximum, increment | Implemented and tested |
| Price lists per group | Through the catalogue's price books |
| Sales on credit: accounts, limits, terms, receivables, overdue | Implemented and tested |
| A buyer on credit receiving an order before paying it, with `phil/commerce-logistics-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and registering the module beside the core. |
| [Customer groups](doc/groups.md) | Groups, memberships, which price a product takes, storage. |
| [Quantity rules](doc/quantities.md) | Minimum, maximum and increment per SKU and group, the constraint, the cart, storage. |
| [Sales on credit](doc/credit.md) | Accounts, purchases on account, the buyer's payments, refunds, storage. |
| [Scope](doc/scope.md) | What the module holds, what it does not yet, and the port of the core it implements. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the module is tested, and how to run its tests. |
| [Reference](doc/reference.md) | Every public type of the module. |

## Quick start

```sh
composer require phil/commerce-bundle phil/commerce-wholesale-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle::class => ['all' => true],
];
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/commerce-bundle`, `phil/ddd-bundle` and `phil/money` 0.1; Doctrine ORM 3.7;
`symfony/config`, `symfony/dependency-injection`, `symfony/uid` and `symfony/validator` 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
