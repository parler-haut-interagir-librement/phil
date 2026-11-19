# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | Counts the characters of names, labels and notes |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core: its orders, `OrderModifier`, payments and `PaymentGateway`, and the `Tip` adjustment |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | Cash, payments, reports |
| `doctrine/orm` | ^3.7 | The mapping attributes of the twelve entities, and the repository adapter |
| `psr/clock` | ^1.0 | The moment an offline sale is imported |
| `symfony/http-foundation` | ^8.2 | The controllers registers send offline sales to and processors send notifications to |
| `symfony/polyfill-php86` | ^1.37 | PHP's `SortDirection`, which the repository's queries pass to the ORM, on PHP 8.5 |
| `symfony/uid` | ^8.2 | Session and movement identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration tree |
| `@hotwired/stimulus` | ^3.0, the application's | The offline queue's controller |
| `symfony/password-hasher`, `symfony/rate-limiter` | ^8.1, ^8.2, suggested | The cashier switch by PIN, when enabled |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | ^8.2, ^3.7, suggested | Serving and registering the Stimulus controller |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-pos-bundle
```

## Registering

Register the core and the module; the order of the two lines does not matter.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommercePosBundle\PhilCommercePosBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommercePosBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Every service reads the module's storage and the core's orders and payments: with DoctrineBundle, the module
registers them with its Doctrine adapter — generate a migration for its twelve tables —; without it, nothing is
registered, and the application declares the services with its own adapters.

## Configuring

Every key is optional.

```yaml
# config/packages/phil_commerce_pos.yaml
phil_commerce_pos:
    recorded_methods: [debit, credit]   # methods taken on a terminal that is not connected; never "cash"
    tip_label: 'Tip'                    # how a tip reads on the receipt
    layaway_deposit: 200000             # the deposit of a layaway, in parts per million of the order
    cash_difference_threshold: 500      # the cash over or short, in minor units, the report flags; none by default
    terminals:
        method: card_present            # the core's payment method of the connected terminals
        fake: false                     # the fake adapter, for tests and development only
    cashier_pin:
        enabled: false                  # the cashier switch by PIN, off by default
        limiter: limiter.cashier_pin    # the rate limiter factory that bounds the attempts, required when enabled
```

The cashier switch needs a rate limiter the application declares, with `symfony/rate-limiter`:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        cashier_pin:
            policy: fixed_window
            limit: 5
            interval: '15 minutes'
```

## Routing

Registers send their offline sales to one URL. Route `POST` requests to the controller, behind the access control
of the staff:

```yaml
# config/routes/pos.yaml
pos_offline_sales:
    path: /register/offline-sales
    methods: [POST]
    controller: Phil\CommercePosBundle\Infrastructure\Http\OfflineSaleController
```

The processors of the connected terminals send their notifications to another, outside any login, since each
adapter verifies the signature:

```yaml
pos_terminal_notifications:
    path: /register/terminal-notifications
    methods: [POST]
    controller: Phil\CommercePosBundle\Infrastructure\Http\TerminalNotificationController
```

## The Stimulus controller

The controller ships in `assets/src/`, declared in `assets/package.json` as `offline-queue`, fetched lazily. With
AssetMapper and StimulusBundle, the module adds `assets/src/` to AssetMapper's paths under
`@phil/commerce-pos-bundle`; enable the package in `assets/controllers.json`, as Symfony Flex writes it:

```json
{
    "controllers": {
        "@phil/commerce-pos-bundle": {
            "offline-queue": {"enabled": true, "fetch": "lazy"}
        }
    }
}
```

It is then named `phil--commerce-pos-bundle--offline-queue`. See [offline.md](offline.md).
