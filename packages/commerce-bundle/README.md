# phil/commerce-bundle

**Orders, prices, taxes, payments and receipts for a Symfony store, with modules plugged on top.**

phil/commerce-bundle is the core of commerce in a Symfony application. It holds orders and their adjustments, with
totals always computed in one currency, through their states: cart, quoted, placed, cancelled. It sells through
channels, a site with its own domains or a point of sale, each with its currency, locale and tax zone. It keeps with
each line the values of the options a catalogue declares — a text, a choice with its image, an ink, a value drawn once
the order is paid —, so the same product with other values is another line, and charges what they add. It taxes each
line after its promotions, with the rates of its configuration or of the application's resolver. It takes payments
through the gateways the application plugs in, and moves each payment only on its processor's verified notifications. It
issues the receipt of a placed order and the credit notes that reverse it, each series numbered without gaps, and
renders them as a PDF invoice. It lets a placed order be paid later — a preorder, installments, a layaway — under one
policy: a deposit, a balance called by a date, a shipment, the goods' arrival or someone of the shop, and the stock
committed on the order or on the balance. Its life cycle is a workflow of `symfony/workflow` that journals each move and
lets someone allowed force one; a placed order changes by versioned changes with their difference due, and expires when
nobody pays it. It offers an order as a gift, wrapped or not, keeps shared wish lists others buy from, takes discounts
granted by hand, takes offline payments and payment links that expire, refunds in two steps, numbers every document type
of a register, and announces an order paid in full, refunded or cancelled for whoever listens. It never converts
currencies, decides tax registration or place of supply, stores payment attempt states itself, talks to a payment
processor, or credits part of a line's quantity.

It is for an application that sells, whether goods or access. Most stores sell access: they install the core and
perhaps `phil/commerce-subscription-bundle`, never a catalogue. Products, price books and promotions belong to
`phil/commerce-catalog-bundle`; logistics and wholesale are packages of their own. A module refuses to load without
the core. The bundle has no resource registry or generic factories.

It is a Symfony bundle that stores orders, payments and receipts through Doctrine and exposes a webhook endpoint for
payment notifications. Amounts are the `Money` of `phil/money`, and taxes are computed by `phil/tax`.

The design rests on ports wired by autoconfiguration: what is sold (`SellableResolver`), with which options
(`SellableOptions`), at what unit price (`UnitPriceResolver`), the processors that recompute an order after each
change (`OrderProcessor`), what a channel sells (`SellableAvailability`), the payment gateways (`PaymentGateway`)
and who draws an option after payment (`OptionDrawer`). A payment
attempt limiter, written as pure functions, keeps a single state per key; a robot verdict adds friction and never
refuses on its own.

## Status

| Part | State |
|---|---|
| `Order`, `OrderLine`, `Adjustment`, `SellableReference`, `Sellable`, `PricingContext` | Implemented and tested |
| Order states — cart, quoted, placed, cancelled —, quotes, amending a placed order, and storage on Doctrine | Implemented and tested |
| Channels: sites and points of sale, their domains, currency, locale, theme and selection | Implemented and tested |
| Payments: split payments, gateways per processor, verified notifications applied once, abandoned payments, refunds, disputes, webhook endpoint | Implemented and tested |
| Deferred sales — preorders, installments, layaways — as one policy: deposit, balance trigger, stock commitment, domain events and the `StockCommitments` port | Implemented and tested |
| `OrderModifier` over `SellableResolver`, `UnitPriceResolver`, `OrderProcessor`, `SellableAvailability` | Implemented and tested |
| Options: `OptionSet`, `OptionDefinition`, `LineConfiguration`, configured lines, `OptionSupplementProcessor`, `OptionDraws`, over `SellableOptions` and `OptionDrawer` | Implemented and tested |
| `TaxOrderProcessor`, `ConfiguredTaxRates` and `phil_commerce.tax_rates` | Implemented and tested |
| `PaymentAttemptKey`, `PaymentAttemptPolicy`, `PaymentAttemptState`, `PaymentAttemptDecision`, `PaymentAttemptLimiter` | Implemented and tested |
| `TaxRateResolver`, `PaymentAttemptStateStore` | Ports; the application implements them, `ConfiguredTaxRates` by default for the first |
| Receipts and credit notes, numbered without gaps, and their PDF invoice | Implemented and tested |
| Order life cycle as the `phil_commerce_order` workflow, its journal, forced moves and statuses | Implemented and tested |
| Gift options, the gift wrapping processor and shared wish lists | Implemented and tested |
| Manual discounts on a line or the order | Implemented and tested |
| Offline payments, payment links that expire, refunds in two steps, `OrderPurchased`, `OrderRefunded`, `OrderCancelled` | Implemented and tested |
| Changes of placed orders, prepared, confirmed and versioned | Implemented and tested |
| Expiry and deletion of unpaid orders, per channel | Implemented and tested |
| The document register, each type listed, by direct link only, or kept for the store | Implemented and tested |
| Partial-quantity credits | Not started |
| Visibility of what is sold — listed, direct link only, absent —, orderable or not, priced or not | Implemented and tested ([channels](doc/channels.md#a-channels-selection)) |
| Subscriptions, catalogue, logistics, wholesale, point of sale, returns, stored value, syndication | Their own packages, each implemented and documented in its README ([modules](doc/modules.md)) |

## Documentation

- [Installation](doc/installation.md): requirements, installing, registering the bundle and its modules, and
  declaring the services and adapters the application uses.
- [Configuration](doc/configuration.md): `phil_commerce.tax_rates`, the services the bundle registers, and every
  decision the application makes.
- [Modules](doc/modules.md): the module packages, how they plug in, the design reference and the choices recorded
  for what is not started.
- [Channels](doc/channels.md): sites and points of sale, the channel of a request, a channel's selection.
- [Orders](doc/orders.md): orders, lines, adjustments, states, storage and `OrderModifier`.
- [Options](doc/options.md): the options a catalogue declares, the configuration of a line, what the options add,
  the draws after payment, storage and receipts.
- [Money](doc/money.md): amounts and currencies from `phil/money`, and what the bundle relies on.
- [Payments](doc/payments.md): paying a placed order, split payments, notifications, the webhook endpoint, refunds
  and what an order received.
- [Deferred sales](doc/deferred-sales.md): preorders, installments and layaways as one policy, its states, the
  balances due, the events and the stock port.
- [Receipts](doc/receipts.md): the issuer, issuing inside a transaction, credit notes, numbering and storage.
- [Life cycle](doc/lifecycle.md): the order workflow, its journal, forced moves and statuses.
- [Gifts and wish lists](doc/gifts.md): gift options, the wrapping, wish lists shared by a link.
- [Manual discounts](doc/discounts.md): discounts granted by hand, on a line or the order.
- [Changes of a placed order](doc/order-changes.md): prepared, confirmed, versioned, with their difference due.
- [Expiry](doc/expiry.md): unpaid orders expired and deleted, per channel.
- [Payment links](doc/payment-links.md): links that open a placed order until they expire.
- [Refunds](doc/refunds.md): refunds granted, then executed.
- [Document register](doc/documents.md): the document types, their series and their visibility.
- [Taxes](doc/taxes.md): the tax processor, the configured rates, the resolver port, and what comes from
  `phil/tax`.
- [Payment attempts](doc/payment-attempts.md): the limiter's rules, policy, keys per order and per client network,
  the single state, decisions with `Retry-After`, and an attempt from end to end.
- [Extension points](doc/extension-points.md): the sixteen ports method by method, the storage contracts, the
  events and what a returns module builds on, their invariants and example adapters.
- [Testing](doc/testing.md): how the bundle is tested, how to run its tests, and how an application tests its
  orders, taxes, limiter and store.
- [Reference](doc/reference.md): the public API by layer, one line per type, and what is not started.

## Quick start

```bash
composer require phil/commerce-bundle
```

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    tax_rates:
        goods:
            QC: { GST: '5', QST: '9.975' }
```

```php
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;

// $modifier: Phil\CommerceBundle\Application\OrderModifier, from the container
$order = Order::open('2026-0001', Currency::of('CAD'));
$modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, $now));
$modifier->changeTaxZone($order, 'QC');
$order->total(); // the lines, then GST and QST on each line

$limiter = new PaymentAttemptLimiter(PaymentAttemptPolicy::of(5, 30, 900));
$key = PaymentAttemptKey::forOrder($order->number);
$decision = $limiter->decide($store->find($key), $now);
```

## Requirements

PHP 8.5 or later with `ext-filter`, `ext-json`, `ext-mbstring` and `ext-sodium`; Doctrine ORM 3.7 and DBAL 4.5;
`phil/ddd-bundle`, `phil/money`, `phil/paged-media` and `phil/tax` 0.1; `psr/clock` 1.0;
`symfony/event-dispatcher-contracts` 3.6; `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation`,
`symfony/uid` and `symfony/workflow` 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

Every file is under this licence alone. The Sylius components served as a design model only.
