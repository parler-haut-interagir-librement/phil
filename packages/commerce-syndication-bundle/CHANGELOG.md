# Changelog

All notable changes to `phil/commerce-syndication-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Fixed

- It requires `phil/commerce-bundle` 0.4, the version published on 2026-11-20, beside which its requirement of 0.2 or
  0.3 kept it from installing.

## 0.1.0-alpha - 2026-11-20

### Added

- `Partner`, `PartnerRole`, `OutgoingMessage`, `ReceivedMessage`, `SyndicatedProduct`, `FeedItem`, `LedgerEntry`, whose
  entry of an order keeps its lines (`lines`, `unitCostOf()`, `quantityOf()`) and whose `credit()` records a return
  under its `return_number`, unique by partner, order and return;
  `SyndicationRefused` and `UnknownPartner`. `ReceivedMessage` is keyed by partner and id. `OutgoingMessage::refused()`,
  `givenUpAt()`, `claim()` and `claimedUntil()`, columns `given_up_at` and `claimed_until`.
- `PublishedPrice` and `PublishedPriceRepository`, table `phil_commerce_syndication_published_price`: the prices a
  supplier published to each reseller, once per change. `pricesInEffectSince()` takes a list of SKUs and returns the
  prices in effect keyed by SKU, so `Inbox` reads those of every line of a forwarded order in two queries, after
  checking every line; `latestPrices()` gives the last price of a SKU sent to many resellers, in one query.
- `FedSku`, table `phil_commerce_syndication_fed_sku`, one row per reseller and SKU fed, kept by the `onFlush`
  listener `FedSkuIndex`; `PartnerRepository::resellersFed()`, through which `Publisher` finds the resellers a
  product update concerns without reading every reseller's list.
- `Partners`, `Publisher`, `Forwarder`, `Inbox`, `Courier`, `Ledger`, `PartnerKey`, `ForwardedOrder`;
  `SyndicatedSellables`, the core's `SellableResolver` and `SellableAvailability` for the type `syndicated`.
  `Publisher` and `Inbox` take a `PublishedPriceRepository`, and `Inbox` the `cost_grace_days`. The `Courier` claims
  each message before sending it, gives a message up at once on a 4xx other than 404, 408, 425 and 429, and holds
  back a partner's other messages for the run once one fails.
- `order.forwarded`: a line must carry a cost published to that reseller within the grace window, and the ledger
  records the supplier's own figure; an order number of more than 64 characters, or amounts that overflow, are
  refused as unreadable (`400`).
- The ports `MessageTransport` and `ForwardedOrderHandler`, the latter tagged
  `phil_commerce_syndication.forwarded_order_handler` by autoconfiguration.
- Shipment tracking: `shipment.sent`, `ShipmentTracking` (`notify()` on the supplier, once per parcel), `PartnerShipment`
  and `PartnerShipmentRepository`, table `phil_commerce_syndication_shipment`; the port `PartnerShipmentHandler`, tagged
  `phil_commerce_syndication.shipment_handler`. Only an order in the ledger with the partner is tracked, within its
  SKUs and units.
- Returns: `return.requested` and `return.received`, `PartnerReturns` (`request()` on the reseller, `receive()` on the
  supplier), `PartnerReturn`, `ReturnState` and `PartnerReturnRepository`, table `phil_commerce_syndication_return`;
  the port `PartnerReturnHandler`, tagged `phil_commerce_syndication.return_handler`. No more is returned than the
  order holds less earlier returns, the credit is computed by each side from its own ledger and a differing one is
  refused; `LedgerRepository::entriesOf()`, and `findEntry()` reads the order's own entry.
- `MessageData`, which reads the data of a received message field by field; the fuzz target `partner-messages`.
- `PartnerRepository`, `MessageRepository` (with `wasReceived()`, which takes the partner, `claim()`,
  `purgeOutgoing()` and `purgeReceived()`), `SyndicatedProductRepository`, `LedgerRepository`, and
  `DoctrineSyndicationRepository`, its queries sorted by PHP's `\SortDirection` through `symfony/polyfill-php86`;
  every moment column a `datetime_utc_immutable`, written and read in UTC, the bundle requiring `doctrine/dbal`.
- `PhilCommerceSyndicationBundle`, extension alias `phil_commerce_syndication` (`identity`, `signing_key`,
  `tolerance`, `max_attempts`, `cost_grace_days`, `retention_days`), which refuses to load without
  `PhilCommerceBundle`.
- `SyndicationWebhookController`, `HttpClientTransport` and the `phil:commerce-syndication:deliver` command.
- Law 25: `ship_to` is cleared from an outgoing message once it is delivered or given up, and `MessageRetention`
  with the `phil:commerce-syndication:purge` command deletes messages after `retention_days`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_commerce_syndication.yaml` on the
  environment variables `SYNDICATION_IDENTITY` and `SYNDICATION_SIGNING_KEY`, and the partners' route in
  `config/routes/phil_commerce_syndication.yaml`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
