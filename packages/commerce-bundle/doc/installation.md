# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `ext-filter` | any | `PaymentAttemptKey::forClientIp()` validates addresses with `filter_var()` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue`, which the commerce types throw |
| `ext-json` | any | The JSON columns of an order's lines and adjustments |
| `ext-mbstring` | any | `ReceiptPdf` writes text in Windows-1252 |
| `ext-sodium` | any | The tokens of payment links and shared wish lists, encoded by `sodium_bin2base64()` |
| `doctrine/orm` | ^3.7 | The mapping attributes of `Order` and the repository adapter |
| `doctrine/dbal` | ^4.5 | The column types of an order's lines and adjustments |
| `phil/money` | ^0.1@dev | `Money` and `Currency`, in which every amount of an order is counted |
| `phil/tax` | ^0.1@dev | `TaxRate` and the multi-rate calculator, with which each line is taxed |
| `phil/paged-media` | ^0.1@dev | The page layout of the PDF invoice |
| `psr/clock` | ^1.0 | The moment a notified payment is announced, from the application's clock |
| `symfony/event-dispatcher-contracts` | ^3.6 | The domain events and the workflow's events, dispatched when the application has a dispatcher |
| `symfony/workflow` | ^8.2 | The `phil_commerce_order` state machine |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle`; the services it registers |
| `symfony/config` | ^8.2 | The `tax_rates` configuration tree |
| `symfony/http-foundation` | ^8.2 | The request and response of the webhook controller |
| `symfony/uid` | ^8.2 | The time-ordered UUID that names a payment |

Orders are stored through DoctrineBundle when the application registers it; no payment processor or HTTP client
library is required.

## Installing

The bundle is not published on Packagist yet. Install it, and `phil/ddd-bundle`, from the repository that holds
them, through Composer repositories of type `path` or `vcs`:

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/*" }
    ],
    "require": {
        "phil/commerce-bundle": "^0.1@dev"
    }
}
```

Once published:

```bash
composer require phil/commerce-bundle
```

## The recipe

The Flex recipe registers the bundle, writes `config/packages/phil_commerce.yaml` with no tax, no offline method and
refunds in two steps, and `config/routes/phil_commerce.yaml` with the route of the webhook endpoint
([payments.md](payments.md#the-webhook-endpoint)). Without Flex, register the bundle and write both files by hand.

## Registering the bundle

```php
// config/bundles.php
return [
    // …
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
];
```

Registering it reads `phil_commerce.tax_rates`, registers `OrderModifier` and the tax processor, tags the services
that implement its ports and, with DoctrineBundle, maps and stores orders (see [configuration.md](configuration.md)).
Generate a migration for the tables `phil_commerce_order`, `phil_commerce_channel`, `phil_commerce_payment`,
`phil_commerce_receipt`, `phil_commerce_quote`, `phil_commerce_deferred_sale`, `phil_commerce_wish_list`,
`phil_commerce_payment_link`, `phil_commerce_order_change`, `phil_commerce_order_state_change`, `phil_commerce_refund`
and `phil_commerce_document_sequence` as for the application's own entities. The migration may also insert a row per
series into `phil_commerce_document_sequence` (`series` `receipt` and `credit_note`, `last_number` 0, or the last
number an earlier system issued): the bundle creates a missing row itself, but a row created beforehand is never raced for.
Route the webhook endpoint ([payments.md](payments.md#the-webhook-endpoint)), and register the module bundles you
sell with beside it ([modules.md](modules.md)).

`PhilDddBundle` does not need to be registered for commerce: only its domain classes are used.

## Declaring the services you use

```yaml
# config/services.yaml
parameters:
    app.payment_attempts.budget: 5
    app.payment_attempts.penalty_step: 30
    app.payment_attempts.window: 900

services:
    app.payment_attempt_policy:
        class: Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy
        factory: [Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy, of]
        arguments:
            - '%app.payment_attempts.budget%'
            - '%app.payment_attempts.penalty_step%'
            - '%app.payment_attempts.window%'

    Phil\CommerceBundle\Domain\Service\PaymentAttemptLimiter:
        arguments: ['@app.payment_attempt_policy']

    Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore: '@App\Commerce\CachePaymentAttemptStateStore'
```

The values above are examples, not recommendations: the budget, the step and the window are the application's
decision (see [payment-attempts.md](payment-attempts.md)). The adapters' contracts are in
[extension-points.md](extension-points.md).
