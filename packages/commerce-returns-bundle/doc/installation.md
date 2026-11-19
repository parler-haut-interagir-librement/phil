# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the module declares and is tested on |
| `ext-mbstring` | any | Counts the characters of a rejection note |
| `phil/commerce-bundle` | ^0.2@dev or ^0.3@dev | The core: its orders, payments and `PaymentGateway` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` and `DomainError` |
| `phil/money` | ^0.1@dev | The value of a return, refunds |
| `doctrine/orm`, `doctrine/dbal` | ^3.7, ^4.5 | The mapping attributes of a return and a credit note, and the repository adapter |
| `symfony/uid` | ^8.2 | Return identifiers |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration tree |
| `symfony/event-dispatcher-contracts` | ^3.6 | The events of each step, when a dispatcher is there |
| `symfony/translation-contracts` | ^3.6 | The words of the slip, when a translator is there |
| `phil/commerce-stored-value-bundle` | suggested | Settling a return as store credit |
| `phil/commerce-catalog-bundle` | suggested | Sets: a part of a composite product refused |
| `phil/commerce-promotions-bundle` | suggested | Specials: a bundle price's discount taken back |
| `phil/commerce-logistics-bundle` | suggested | The window counted from the order's last shipment |
| `phil/paged-media` | suggested | Return slips drawn as PDF |
| `symfony/rate-limiter` | suggested | The returns each customer asks for, bounded |

## Installing

```sh
composer require phil/commerce-bundle phil/commerce-returns-bundle
```

## Registering

With Symfony Flex, the recipe of each package registers its bundle; the module's defaults need no configuration file.
Without Flex:

Register the core and the module; the order of the lines does not matter. Register
`phil/commerce-stored-value-bundle` too for store credit, `phil/commerce-catalog-bundle` for sets,
`phil/commerce-promotions-bundle` for specials and `phil/commerce-logistics-bundle` for shipment dates: the module then
wires each on its own.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle::class => ['all' => true],
];
```

Registered without the core, the module fails the container build:

```text
PhilCommerceReturnsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

The services read the module's storage and the core's orders and payments: with DoctrineBundle, the module registers
them with its Doctrine adapter — generate a migration for its tables `phil_commerce_return` and
`phil_commerce_return_credit_note` —; without it, nothing is registered.

## Configuring

```yaml
# config/packages/phil_commerce_returns.yaml
phil_commerce_returns:
    window_days: 30               # how long goods may come back
    window_from: placed           # placed, or shipped: from the order's last shipment
    restocking_fee: 0             # kept on the lines the shop is not at fault for, in parts per million
    delivery_refund: shops_fault  # never, shops_fault or whole_order
    expiry_days: ~                # how long an authorized return waits for its goods; ~ for ever
    rate_limiter: ~               # a symfony/rate-limiter factory bounding each customer's requests
    slips:
        prefix: 'RS-'
        digits: 8
```

A rate limit names a factory of `framework.rate_limiter`:

```yaml
framework:
    rate_limiter:
        returns:
            policy: sliding_window
            limit: 5
            interval: '1 day'

phil_commerce_returns:
    rate_limiter: limiter.returns
```

Expiry runs from a scheduled task — a Symfony Scheduler message or a cron command — that calls
`Returns::expire($clock->now())` and commits.

## Restocking

Implement `Restocking` to put resellable units back on the shelf — a stock movement of
`phil/commerce-logistics-bundle`, for instance; autoconfiguration tags the service.
