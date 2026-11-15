# Modules

The core of commerce is this bundle; every other part is **a package of its own** that plugs into it. Most stores
sell access rather than goods: they install the core and perhaps subscriptions, and never the catalogue, logistics
or wholesale.

| Package | Bundle class | Holds | State |
|---|---|---|---|
| `phil/commerce-bundle` | `PhilCommerceBundle` | Orders and adjustments, the ports below, taxes per line, payments and their webhook, receipts and credit notes with their PDF invoice, the payment attempt limiter | Implemented; partial-quantity credits to come |
| `phil/commerce-subscription-bundle` | `PhilCommerceSubscriptionBundle` | Offers and tiers, periods, trials, renewal, dunning, cancellation at the end of the paid period, features per tier | Implemented; see its README for what is not started |
| `phil/commerce-catalog-bundle` | `PhilCommerceCatalogBundle` | Products, price books, promotions and their codes, the products a channel sells | Implemented; variants, taxonomy, packs and reviews not started |
| `phil/commerce-logistics-bundle` | `PhilCommerceLogisticsBundle` | Stock per location, order reservations, shipments in parcels, delivery methods charged per zone | Implemented; see its README |
| `phil/commerce-wholesale-bundle` | `PhilCommerceWholesaleBundle` | Customer groups with their own prices and minimum order, sales on credit with net terms | Implemented; see its README |
| `phil/commerce-pos-bundle` | `PhilCommercePosBundle` | A cash register for counter sales: tills, shifts, cash counts, layaways, sales rung up offline | Implemented; see its README |
| `phil/commerce-returns-bundle` | `PhilCommerceReturnsBundle` | Return requests, from authorization to a refund, store credit or an exchange | Implemented; see its README |
| `phil/commerce-stored-value-bundle` | `PhilCommerceStoredValueBundle` | Gift cards and store credit, sold, loaded and spent as payments | Implemented; see its README |
| `phil/commerce-syndication-bundle` | `PhilCommerceSyndicationBundle` | Another shop's products resold, with orders forwarded to the supplier who ships | Implemented; see its README |

Installing a module is enabling it: there is no list of enabled modules to keep. A module refuses to load without
the core.

## How a module plugs in

The core defines seven tags, which autoconfiguration fills with every service implementing the matching port
([extension-points.md](extension-points.md)). `OrderModifier` reads four of them, `LineOptions` the options,
`Payments` the gateways and `OptionDraws` the drawers:

| Port | Tag | Implemented by |
|---|---|---|
| `SellableResolver` | `phil_commerce.sellable_resolver` | The catalogue (`product`), subscriptions (`offer`), the application for anything else it sells |
| `UnitPriceResolver` | `phil_commerce.unit_price_resolver` | The catalogue's price books, wholesale's price lists |
| `OrderProcessor` | `phil_commerce.order_processor` | The catalogue's promotions (before taxes), the core's option supplements (before taxes) and taxes, logistics' shipping (after promotions, before taxes), subscriptions' proration |
| `SellableAvailability` | `phil_commerce.sellable_availability` | The catalogue's selections and offers, syndication's resold products |
| `SellableOptions` | `phil_commerce.sellable_options` | A catalogue, for the options it declares on its products |
| `OptionDrawer` | `phil_commerce.option_drawer` | A module or the application, for each value drawn after payment |
| `PaymentGateway` | `phil_commerce.payment_gateway` | Stored value, the register, returns, wholesale accounts, and a gateway per processor of the application |

A module never changes the core: it adds services that implement these ports, and its own entities and screens.
A module that numbers documents of its own declares their types to the register with `DocumentTypes`, tagged
`phil_commerce.document_types` ([documents.md](documents.md)).

A package outside commerce — a loyalty programme, an activity feed — follows sales through `OrderPurchased` and the
events of the `phil_commerce_order` workflow, never through a dependency of the core on it
([payments.md](payments.md#an-order-paid-in-full), [lifecycle.md](lifecycle.md)).

## Design reference

The decomposition follows the commerce components of Sylius 2.3 (Taxation, Order, Inventory, Shipping, Payment,
Promotion, Product, Attribute, Taxonomy, Currency): the shape of the models and calculators, and the order
processors that recompute adjustments. Sylius serves as a model only, and the bundles do
without `Sylius\Resource`, its registry, factories and repositories, and without `webmozart/assert`: value objects
validate themselves. Subscriptions have no Sylius counterpart and are designed without a model.

Choices recorded so that they are not undone by accident:

- payments through gateways of our own, one per processor, rather than Payum;
- the payment state coming only from the processor's signed notifications, never from the browser's return
  (built: [payments.md](payments.md));
- amounts computed on the server from the order and its adjustments, never taken from a form (built);
- refunds and disputes as states of the payment, and a credit note reversing a receipt rather than editing it
  (built: [receipts.md](receipts.md));
- receipts numbered in series without gaps (built).
